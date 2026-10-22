<?php

namespace App\Services\Interop;

use App\Exceptions\FlujoException;
use App\Jobs\EnviarComunicacionDocDigital;
use App\Models\Archivo;
use App\Models\ComunicacionAnexo;
use App\Models\ComunicacionOficial;
use App\Models\DocDigitalEntidad;
use App\Models\DocDigitalUsuario;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Institucion;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;
use App\Services\Admin\CodificacionService;
use App\Services\AuditoriaService;
use App\Services\ExpedienteService;
use App\Services\FileVault;
use App\Services\Firma\ArchivoFirmaService;
use App\Services\Interop\DocDigital\DocDigitalClient;
use App\Services\Interop\DocDigital\DocDigitalException;
use App\Support\UploadValidator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Comunicaciones oficiales del Estado vía DocDigital. Mismo principio que las notificaciones: el sistema NUNCA afirma
 * haber enviado algo sin evidencia (ID de DocDigital). Modo `outbox` (defecto): queda "pendiente" hasta registrar
 * el ID/folio obtenido manualmente en DocDigital. Solo documentos vigentes; en modos API se exige PDF con FEA real.
 */
class ComunicacionService
{
    public function __construct(
        private readonly AuditoriaService $audit,
        private readonly FileVault $vault,
        private readonly DocDigitalClient $client,
        private readonly CodificadorService $codificador,
        private readonly ExpedienteService $expedientes,
        private readonly ArchivoFirmaService $firmas,
        private readonly AccesoRestringidoService $restringidos,
    ) {}

    public function modoApi(): bool
    {
        return in_array(config('sgd.docdigital.mode'), ['mock', 'demo', 'prod'], true);
    }

    /** @param list<int> $archivoIds archivos del expediente que se envían como anexos (máx. DOCDIGITAL_MAX_ANEXOS) */
    public function crearSaliente(Expediente $e, Documento $d, string $institucionCodigo, string $asunto, Usuario $u, array $archivoIds = [], array $usuarioIds = []): ComunicacionOficial
    {
        $inst = $this->codificador->institucionActiva($institucionCodigo);
        if (! $inst) {
            throw new FlujoException('La institución destinataria no existe en el Codificador.');
        }

        return $this->crearComun($e, $d, ['codigo' => $inst->codigo, 'nombre' => $inst->nombre, 'entidad_id' => null], $asunto, $u, $archivoIds, $usuarioIds);
    }

    /**
     * Comunicación saliente a una entidad del directorio de DocDigital (y, si se indica, a personas habilitadas de esa entidad).
     * Si la entidad corresponde a una institución del Codificador, se usa esa institución.
     *
     * @param  list<int>  $archivoIds
     * @param  list<int>  $usuarioIds  ids de personas en DocDigital (docdigital_usuarios.usuario_id)
     */
    public function crearSalienteEntidad(Expediente $e, Documento $d, int $entidadId, string $asunto, Usuario $u, array $archivoIds = [], array $usuarioIds = []): ComunicacionOficial
    {
        $ent = DocDigitalEntidad::query()->where('entidad_id', $entidadId)->where('vigente', true)->first();
        if (! $ent) {
            throw new FlujoException('La entidad no figura en el directorio de DocDigital (actualícelo desde el panel de DocDigital).');
        }
        $inst = Institucion::query()->where('docdigital_entidad_id', $entidadId)->where('activo', true)->first();

        return $this->crearComun($e, $d, ['codigo' => $inst?->codigo, 'nombre' => $inst?->nombre ?? $ent->nombre, 'entidad_id' => $entidadId], $asunto, $u, $archivoIds, $usuarioIds);
    }

    /** @param array{codigo:?string,nombre:string,entidad_id:?int} $dest */
    private function crearComun(Expediente $e, Documento $d, array $dest, string $asunto, Usuario $u, array $archivoIds, array $usuarioIds): ComunicacionOficial
    {
        $personas = $this->validarPersonas($usuarioIds, $dest['entidad_id'] ?? Institucion::query()->where('codigo', (string) $dest['codigo'])->value('docdigital_entidad_id'));
        $archivos = $this->validarAnexos($e, $u, $archivoIds);
        $asunto = trim($asunto);
        if (mb_strlen($asunto) < 3 || mb_strlen($asunto) > 190 || preg_match('/[\r\n]/', $asunto)) {
            throw new FlujoException('El asunto debe tener entre 3 y 190 caracteres, en una línea.');
        }
        if ($d->expediente_id !== $e->id || $d->estado !== 'vigente') {
            throw new FlujoException('Solo se comunican documentos vigentes del expediente.');
        }
        if ($this->modoApi() && config('sgd.docdigital.mode') !== 'mock' && ! $d->pdf_firmado_ruta) {
            throw new FlujoException('DocDigital exige un PDF con firma electrónica avanzada; este documento no tiene firma real (¿firma simulada?).');
        }
        $dup = ComunicacionOficial::query()->where('direccion', 'saliente')->where('documento_id', $d->id)->whereIn('estado', ['pendiente', 'enviada'])
            ->when($dest['entidad_id'], fn ($q) => $q->where('destino_entidad_id', $dest['entidad_id']), fn ($q) => $q->where('institucion_codigo', $dest['codigo']))->exists();
        if ($dup) {
            throw new FlujoException('Ya existe una comunicación de este documento a esa institución.');
        }
        $origen = $e->origen === 'docdigital' ? ComunicacionOficial::query()->where('id', $e->comunicacion_id)->first() : null;
        $c = ComunicacionOficial::query()->create([
            'responde_a_id' => $origen?->id, 'direccion' => 'saliente', 'expediente_id' => $e->id, 'documento_id' => $d->id, 'institucion_codigo' => $dest['codigo'], 'institucion_nombre' => $dest['nombre'], 'destino_entidad_id' => $dest['entidad_id'],
            'destino_usuarios' => $personas ? json_encode($personas, JSON_UNESCAPED_UNICODE) : null, 'asunto' => $asunto, 'estado' => 'pendiente', 'creado_por' => $u->id, 'unidad_id' => $e->unidad_id,
        ]);
        foreach ($archivos as $a) {
            ComunicacionAnexo::query()->create(['comunicacion_id' => $c->id, 'archivo_id' => $a->id, 'nombre' => $a->nombre_original, 'estado' => 'pendiente', 'tamano' => $a->tamano]);
        }
        $this->audit->registrar('docdigital.crear', 'comunicacion', $c->id, ['destino' => $dest['codigo'] ?? ('entidad '.$dest['entidad_id']), 'documento' => $d->id, 'anexos' => count($archivos), 'personas' => count($personas)]);
        if ($this->modoApi()) {
            EnviarComunicacionDocDigital::dispatch($c->id);
        }

        return $c;
    }

    /**
     * Personas de DocDigital a las que se dirige el despacho además de la entidad: deben figurar vigentes en el directorio y pertenecer a la entidad destinataria.
     *
     * @param  list<int|string>  $ids
     * @return list<array{id:int,nombre:string}>
     */
    private function validarPersonas(array $ids, mixed $entidadId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)));
        if (! $ids) {
            return [];
        }
        if (count($ids) > 20) {
            throw new FlujoException('Puede dirigir el documento a hasta 20 personas.');
        }
        $f = DocDigitalUsuario::query()->whereIn('usuario_id', $ids)->where('vigente', true)->get();
        if ($f->count() !== count($ids) || ! $entidadId || $f->contains(fn ($p) => (int) $p->entidad_id !== (int) $entidadId)) {
            throw new FlujoException('Las personas destinatarias deben estar habilitadas en DocDigital y pertenecer a la entidad destinataria.');
        }

        return $f->map(fn ($p) => ['id' => (int) $p->usuario_id, 'nombre' => $p->nombre])->values()->all();
    }

    /**
     * Anexos de una comunicación saliente: archivos del propio expediente, con acceso del usuario (restringidos), sin comprobantes,
     * sin repetir, y dentro del máximo y del tamaño que admite DocDigital (50 MB c/u).
     *
     * @param  list<int>  $ids
     * @return list<Archivo>
     */
    private function validarAnexos(Expediente $e, Usuario $u, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $max = max(0, min(50, (int) config('sgd.docdigital.max_anexos', 10)));
        if (count($ids) > $max) {
            throw new FlujoException("Puede enviar hasta $max anexos por comunicación.");
        }
        $out = [];
        foreach ($ids as $id) {
            $a = Archivo::query()->where('expediente_id', $e->id)->where('ambito', '!=', 'comprobante')->find($id);
            if (! $a) {
                throw new FlujoException('Uno de los anexos no pertenece al expediente.');
            }
            if ($a->tamano > (int) config('sgd.docdigital.max_bytes_anexo', 50 * 1048576)) {
                throw new FlujoException('El anexo «'.$a->nombre_original.'» excede los 50 MB que admite DocDigital.');
            }
            try {
                $this->restringidos->exigirArchivo($u, $a, 'anexar_docdigital');
            } catch (HttpException|HttpResponseException) {
                throw new FlujoException('No tiene autorización para enviar el archivo «'.$a->nombre_original.'» (restringido).');
            }
            $out[] = $a;
        }

        return $out;
    }

    /** Envío idempotente (lo invoca el job). Falla cerrada: ante error queda "fallida" y se reintenta. */
    public function enviar(ComunicacionOficial $c): ComunicacionOficial
    {
        if ($c->estado === 'enviada' || $c->direccion !== 'saliente') {
            return $c;
        }
        $d = Documento::query()->with('tipo')->findOrFail($c->documento_id);
        try {
            $ruta = $d->pdf_firmado_ruta ?: $d->pdf_ruta;
            $sha = $d->pdf_firmado_ruta ? $d->pdf_firmado_sha256 : $d->pdf_sha256;
            $pdf = $this->vault->get((string) $ruta, (string) $sha); // verifica integridad antes de enviar
            $inst = Institucion::query()->where('codigo', (string) $c->institucion_codigo)->first();
            $filasAnexo = ComunicacionAnexo::query()->with('archivo')->where('comunicacion_id', $c->id)->orderBy('id')->get();
            $anexos = $filasAnexo->filter(fn ($x) => $x->archivo)->map(fn ($x) => ['nombre' => $x->archivo->nombre_original, 'bytes' => $this->vault->get($x->archivo->ruta, $x->archivo->sha256)])->values()->all(); // verifica integridad
            $r = $this->client->enviar($pdf, 'documento-'.$d->numero.'-'.$d->anio.'.pdf', [
                'asunto' => $c->asunto, 'destino_codigo' => (string) ($c->institucion_codigo ?? ''), 'destino_nombre' => $inst?->nombre ?? $c->institucion_nombre, 'destino_entidad_id' => $c->destino_entidad_id ?: $inst?->docdigital_entidad_id,
                'destino_usuarios' => array_map(fn ($p) => (int) $p['id'], (array) json_decode((string) $c->destino_usuarios, true)),
                'origen_codigo' => CodificacionService::institucion(), 'numero' => $d->numeroFormateado(), 'tipo' => $d->tipo->nombre, 'reservado' => (bool) ($d->sensible || $d->restringido), 'anexos' => $anexos,
                'referencia' => $c->responde_a_id ? ComunicacionOficial::query()->where('id', $c->responde_a_id)->value('evidencia') : null, // A VALIDAR
            ]);
        } catch (\Throwable $e) {
            $c->forceFill(['estado' => 'fallida', 'intentos' => $c->intentos + 1, 'ultimo_error' => $e instanceof DocDigitalException ? mb_substr($e->getMessage(), 0, 120) : 'Fallo de envío'])->save();
            $this->audit->registrar('docdigital.fallo', 'comunicacion', $c->id, ['intento' => $c->intentos], false, ['run' => null, 'rol' => 'sistema']);
            throw $e;
        }
        $c->forceFill(['estado' => 'enviada', 'evidencia_tipo' => $r['simulado'] ? 'simulado' : 'docdigital_id', 'evidencia' => $r['id'], 'evidencia_en' => now(), 'ultimo_error' => null])->save();
        $this->marcarAnexosEnviados($c, $r['anexos'] ?? []);
        $this->refrescarEstado($c); // best-effort: DocDigital deja el documento «pendiente de despacho» hasta que la Oficina de Partes lo despache
        $this->audit->registrar('docdigital.enviada', 'comunicacion', $c->id, ['simulado' => $r['simulado']], false, ['run' => null, 'rol' => 'sistema']);

        return $c;
    }

    /** Consulta el estado de tramitación en DocDigital. Nunca lanza: un fallo de consulta no invalida el envío. */
    public function refrescarEstado(ComunicacionOficial $c): void
    {
        if ($c->direccion !== 'saliente' || $c->evidencia_tipo !== 'docdigital_id' || ! $this->modoApi()) {
            return;
        }
        try {
            $e = $this->client->estado((string) $c->evidencia);
        } catch (\Throwable) {
            return;
        }
        if ($e !== null && $e['estado'] !== $c->dd_estado) {
            $c->forceFill(['dd_estado' => $e['estado'], 'dd_estado_en' => now()])->save();
            $this->audit->registrar('docdigital.estado', 'comunicacion', $c->id, ['estado' => $e['estado']], false, ['run' => null, 'rol' => 'sistema']);
        }
    }

    /** Actualiza el estado de lo enviado que aún puede cambiar (despacho, recepción, devolución). @return int cantidad consultada */
    public function refrescarEstados(int $limite = 50): int
    {
        if (! $this->modoApi()) {
            return 0;
        }
        $n = 0;
        $q = ComunicacionOficial::query()->where('direccion', 'saliente')->where('evidencia_tipo', 'docdigital_id')->where('estado', 'enviada')
            ->where(fn ($w) => $w->whereNull('dd_estado')->orWhereNotIn('dd_estado', ComunicacionOficial::DD_FINALES))->orderBy('dd_estado_en')->orderBy('id')->limit($limite);
        foreach ($q->get() as $c) {
            $this->refrescarEstado($c);
            $n++;
        }

        return $n;
    }

    /** @param list<array{id:?string,nombre:string}> $devueltos anexos tal como los devolvió DocDigital */
    private function marcarAnexosEnviados(ComunicacionOficial $c, array $devueltos): void
    {
        foreach (ComunicacionAnexo::query()->where('comunicacion_id', $c->id)->orderBy('id')->get() as $i => $fila) {
            $d = collect($devueltos)->firstWhere('nombre', $fila->nombre) ?? ($devueltos[$i] ?? null);
            $fila->forceFill(['estado' => 'enviado', 'id_externo' => $d['id'] ?? null])->save();
        }
    }

    public function registrarEvidencia(ComunicacionOficial $c, string $folio): ComunicacionOficial
    {
        $folio = trim($folio);
        if ($c->direccion !== 'saliente' || $c->estado === 'enviada' || $folio === '' || mb_strlen($folio) > 120 || preg_match('/[\r\n]/', $folio)) {
            throw new FlujoException('No se puede registrar esa evidencia.');
        }
        if ($this->modoApi()) {
            throw new FlujoException('En modo API la evidencia la registra el sistema.');
        }
        $c->forceFill(['estado' => 'enviada', 'evidencia_tipo' => 'docdigital_manual', 'evidencia' => $folio, 'evidencia_en' => now(), 'ultimo_error' => null])->save();
        ComunicacionAnexo::query()->where('comunicacion_id', $c->id)->where('estado', 'pendiente')->update(['estado' => 'enviado']);
        $this->audit->registrar('docdigital.evidencia', 'comunicacion', $c->id);

        return $c;
    }

    /** @return array{nuevas:int,omitidas:int} */
    public function sincronizarRecibidos(): array
    {
        $unidad = Unidad::query()->where('codigo', config('sgd.docdigital.unidad_recepcion'))->where('activo', true)->first();
        if (! $unidad) {
            throw new DocDigitalException('La unidad de recepción (DOCDIGITAL_UNIDAD_RECEPCION) no existe o está inactiva.');
        }
        $res = ['nuevas' => 0, 'omitidas' => 0];
        foreach ($this->client->recibidos() as $r) {
            if (ComunicacionOficial::query()->where('direccion', 'entrante')->where('evidencia_tipo', 'docdigital_id')->where('evidencia', $r['id'])->exists()) {
                continue;
            }
            try {
                UploadValidator::validarBytes($r['pdf']);
                if (! str_starts_with($r['pdf'], '%PDF-')) {
                    throw new \InvalidArgumentException('no es PDF');
                }
            } catch (\InvalidArgumentException) {
                $res['omitidas']++;
                $this->audit->registrar('docdigital.entrante_rechazado', null, null, ['id' => $r['id']], false, ['run' => null, 'rol' => 'sistema']);

                continue;
            }
            $anexosRecibidos = $this->bajarAnexos($r); // fuera de la transacción: son llamadas de red
            $rutas = [];
            try {
                DB::transaction(function () use ($r, $unidad, &$res, &$rutas, $anexosRecibidos) {
                    $f = $this->vault->put($r['pdf']);
                    $rutas[] = $f['ruta'];
                    $a = new Archivo([
                        'ambito' => 'docdigital', 'nombre_original' => 'docdigital-'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $r['id']).'.pdf', 'mime' => 'application/pdf', 'tamano' => $f['tamano'],
                        'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => mb_substr($r['asunto'], 0, 190), 'nivel_acceso' => 'reservado', 'sensible' => true, 'subido_por' => 'docdigital',
                    ]);
                    $this->firmas->analizar($a, $r['pdf']); // las comunicaciones del Estado suelen venir con firma electrónica: se reconoce y se verifica
                    $a->save();
                    $origenCodigo = $r['origen_codigo'] ?? (! empty($r['origen_entidad_id']) ? Institucion::query()->where('docdigital_entidad_id', (int) $r['origen_entidad_id'])->value('codigo') : null);
                    $c = ComunicacionOficial::query()->create([
                        'direccion' => 'entrante', 'institucion_codigo' => $origenCodigo ? mb_substr((string) $origenCodigo, 0, 40) : null,
                        'institucion_nombre' => $r['origen_nombre'] ? mb_substr($r['origen_nombre'], 0, 190) : null, 'asunto' => mb_substr($r['asunto'], 0, 190), 'estado' => 'recibida',
                        'evidencia_tipo' => 'docdigital_id', 'evidencia' => $r['id'], 'evidencia_en' => now(), 'archivo_id' => $a->id, 'unidad_id' => $unidad->id, 'acuse_estado' => 'pendiente',
                    ]);
                    // Todo lo recibido genera un expediente con identificador único; el PDF original queda en su índice.
                    $e = $this->expedientes->crearDesdeComunicacion($c, $unidad);
                    $c->forceFill(['expediente_id' => $e->id])->save();
                    $a->forceFill(['expediente_id' => $e->id])->save();
                    foreach ($anexosRecibidos as $x) {
                        $this->guardarAnexoRecibido($c, $e, $x, $rutas);
                    }
                    $res['nuevas']++;
                });
            } catch (\Throwable $t) {
                foreach ($rutas as $ruta) {
                    $this->vault->delete($ruta);
                }
                throw $t;
            }
        }
        $this->audit->registrar('docdigital.sincronizar', null, null, $res, false, ['run' => null, 'rol' => 'sistema']);
        DocDigitalEstado::registrar($res);

        return $res;
    }

    /**
     * Descarga y revisa los anexos de un documento recibido. Solo se aceptan los formatos que el administrador habilitó (validación por
     * contenido, sin macros ni código); lo demás se OMITE y queda registrado con el motivo. Nunca se visitan URLs que venga en la respuesta.
     *
     * @param  array<string,mixed>  $r
     * @return list<array{nombre:string,id:string,bytes:?string,mime:?string,motivo:?string}>
     */
    private function bajarAnexos(array $r): array
    {
        $out = [];
        $max = max(0, min(50, (int) config('sgd.docdigital.max_anexos', 10)));
        foreach (array_slice((array) ($r['anexos'] ?? []), 0, $max + 20) as $i => $a) {
            $base = ['nombre' => (string) $a['nombre'], 'id' => (string) $a['id'], 'bytes' => null, 'mime' => null, 'motivo' => null];
            if ($i >= $max) {
                $out[] = ['motivo' => "excede el máximo de $max anexos por comunicación"] + $base;

                continue;
            }
            if (($a['tipo'] ?? 'anexo') !== 'anexo') {
                $out[] = ['motivo' => 'enlace externo: no se descarga'] + $base;

                continue;
            }
            try {
                $bytes = $this->client->descargarAnexo((string) $r['id'], (string) $a['id']);
            } catch (DocDigitalException) {
                $bytes = null;
            }
            if ($bytes === null) {
                $out[] = ['motivo' => 'no se pudo descargar (vacío, excede 50 MB o error de DocDigital)'] + $base;

                continue;
            }
            try {
                $t = UploadValidator::validarBytes($bytes);
            } catch (\InvalidArgumentException $e) {
                $out[] = ['motivo' => mb_substr($e->getMessage(), 0, 180)] + $base;
                $this->audit->registrar('docdigital.anexo_rechazado', null, null, ['documento' => $r['id'], 'anexo' => $a['id']], false, ['run' => null, 'rol' => 'sistema']);

                continue;
            }
            $out[] = ['bytes' => $bytes, 'mime' => $t['mime']] + $base;
        }

        return $out;
    }

    /** @param array{nombre:string,id:string,bytes:?string,mime:?string,motivo:?string} $x @param list<string> $rutas */
    private function guardarAnexoRecibido(ComunicacionOficial $c, Expediente $e, array $x, array &$rutas): void
    {
        $nombre = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($x['nombre'])) ?: 'anexo', 0, 190);
        if ($x['bytes'] === null) {
            ComunicacionAnexo::query()->create(['comunicacion_id' => $c->id, 'nombre' => $nombre, 'id_externo' => $x['id'], 'estado' => 'omitido', 'motivo' => $x['motivo']]);

            return;
        }
        $f = $this->vault->put($x['bytes']);
        $rutas[] = $f['ruta'];
        $a = new Archivo([
            'expediente_id' => $e->id, 'ambito' => 'dd_anexo', 'nombre_original' => $nombre, 'mime' => $x['mime'], 'tamano' => $f['tamano'], 'sha256' => $f['sha256'], 'ruta' => $f['ruta'],
            'titulo' => mb_substr('Anexo: '.$nombre, 0, 190), 'nivel_acceso' => 'reservado', 'sensible' => true, 'subido_por' => 'docdigital',
        ]);
        $this->firmas->analizar($a, $x['bytes']);
        $a->save();
        ComunicacionAnexo::query()->create(['comunicacion_id' => $c->id, 'archivo_id' => $a->id, 'nombre' => $nombre, 'id_externo' => $x['id'], 'estado' => 'recibido', 'tamano' => $f['tamano']]);
    }

    /** Acuse de recibo por la API (o simulado en mock). En outbox use acusarManual(). */
    public function acusarRecibo(ComunicacionOficial $c, Usuario $u): ComunicacionOficial
    {
        $this->validarAcuse($c);
        if (! $this->modoApi()) {
            throw new FlujoException('DocDigital está en modo outbox: registre el acuse hecho en DocDigital como evidencia manual.');
        }
        try {
            $r = $this->client->acusarRecibo((string) $c->evidencia);
        } catch (DocDigitalException $e) {
            $this->audit->registrar('docdigital.acuse_fallo', 'comunicacion', $c->id);
            throw new FlujoException($e->getMessage(), 0, $e);
        }

        return $this->marcarAcuse($c, $u, $r['simulado'] ? 'simulado' : 'api', $r['evidencia']);
    }

    /** Devuelve/rechaza una comunicación recibida (PUT /documentos/recibidos/{id}/devolver). Solo en modo API; el motivo queda registrado. */
    public function devolver(ComunicacionOficial $c, Usuario $u, string $motivo): ComunicacionOficial
    {
        $this->validarAcuse($c);
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 3 || mb_strlen($motivo) > 255) {
            throw new FlujoException('Indique el motivo de la devolución (entre 3 y 255 caracteres).');
        }
        if (! $this->modoApi()) {
            throw new FlujoException('DocDigital está en modo outbox: la devolución se hace en DocDigital.');
        }
        try {
            $r = $this->client->devolver((string) $c->evidencia, $motivo);
        } catch (DocDigitalException $e) {
            $this->audit->registrar('docdigital.devolucion_fallo', 'comunicacion', $c->id);
            throw new FlujoException($e->getMessage(), 0, $e);
        }
        $c->forceFill(['acuse_estado' => 'devuelto', 'acuse_tipo' => $r['simulado'] ? 'simulado' : 'api', 'acuse_evidencia' => mb_substr($r['evidencia'].' · '.$motivo, 0, 255), 'acuse_en' => now(), 'acuse_por_id' => $u->id])->save();
        $this->audit->registrar('docdigital.devolucion', 'comunicacion', $c->id, ['motivo' => $motivo]);

        return $c;
    }

    public function acusarManual(ComunicacionOficial $c, Usuario $u, string $evidencia): ComunicacionOficial
    {
        $this->validarAcuse($c);
        $evidencia = trim($evidencia);
        if ($this->modoApi()) {
            throw new FlujoException('En modo API el acuse lo registra el sistema.');
        }
        if ($evidencia === '' || mb_strlen($evidencia) > 200 || preg_match('/[\r\n]/', $evidencia)) {
            throw new FlujoException('Indique la referencia del acuse emitido en DocDigital (hasta 200 caracteres).');
        }

        return $this->marcarAcuse($c, $u, 'manual', $evidencia);
    }

    private function validarAcuse(ComunicacionOficial $c): void
    {
        if ($c->direccion !== 'entrante' || $c->acuse_estado !== 'pendiente') {
            throw new FlujoException('Esta comunicación no tiene un acuse de recibo pendiente.');
        }
    }

    private function marcarAcuse(ComunicacionOficial $c, Usuario $u, string $tipo, string $evidencia): ComunicacionOficial
    {
        $c->forceFill(['acuse_estado' => 'registrado', 'acuse_tipo' => $tipo, 'acuse_evidencia' => $evidencia, 'acuse_en' => now(), 'acuse_por_id' => $u->id])->save();
        $this->audit->registrar('docdigital.acuse', 'comunicacion', $c->id, ['tipo' => $tipo]);

        return $c;
    }
}
