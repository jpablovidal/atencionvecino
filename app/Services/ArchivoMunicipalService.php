<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Microforma;
use App\Models\MicroformaEvento;
use App\Models\MicroformaItem;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\Firma\ArchivoFirmaService;
use App\Support\UploadValidator;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Archivo municipal: gestión de microformas.
 * Borrador → en revisión → certificada (acta del ministro de fe, con separación de funciones) y, después, disposición de los
 * originales con doble control. Los archivos maestros se guardan cifrados (FileVault) con su SHA-256; la certificación fija un hash del
 * conjunto y cada acción relevante queda en la cadena de custodia (solo escritura) y en la auditoría HMAC.
 * El sistema registra y protege; NO reemplaza la certificación legal (ver docs/NORMATIVA.md, «A VALIDAR»).
 */
class ArchivoMunicipalService
{
    public const MAX_ARCHIVOS = 300;

    public const MAX_ITEMS = 3000;

    public function __construct(private readonly AuditoriaService $audit, private readonly FileVault $vault, private readonly ArchivoFirmaService $firmas) {}

    // ---------------------------------------------------------------- utilidades
    private function exigir(Usuario $u, string $permiso, string $accion): void
    {
        if (! $u->tienePermiso($permiso)) {
            throw new FlujoException("No tiene permiso para {$accion}.");
        }
    }

    private function bloquear(Microforma $m): Microforma
    {
        return Microforma::query()->lockForUpdate()->findOrFail($m->id);
    }

    public function evento(Microforma $m, string $tipo, Usuario $u, array $detalle = []): void
    {
        MicroformaEvento::query()->create(['microforma_id' => $m->id, 'tipo' => $tipo, 'detalle' => $detalle ? (string) json_encode($detalle, JSON_UNESCAPED_UNICODE) : null, 'usuario_id' => $u->id, 'created_at' => now()]);
    }

    /** Hash del conjunto de archivos maestros (orden + SHA-256 de cada uno). */
    public function hashConjunto(Microforma $m): string
    {
        $m->unsetRelation('archivos');

        return hash('sha256', $m->archivos()->get()->map(fn ($a) => $a->pivot->orden.':'.$a->sha256)->implode("\n"));
    }

    /** @param array<string,mixed> $d @return array<string,mixed> */
    private function validarCampos(array $d): array
    {
        $titulo = TramiteService::limpiarTexto($d['titulo'] ?? '');
        if (mb_strlen($titulo) < 5 || mb_strlen($titulo) > 190) {
            throw new FlujoException('El título debe tener entre 5 y 190 caracteres.');
        }
        $desc = TramiteService::limpiarTexto($d['descripcion'] ?? '', true);
        if (mb_strlen($desc) > 2000) {
            throw new FlujoException('La descripción admite hasta 2000 caracteres.');
        }
        $soporte = (string) ($d['tipo_soporte'] ?? '');
        if (! isset(Microforma::SOPORTES[$soporte])) {
            throw new FlujoException('Tipo de soporte inválido.');
        }
        $serie = TramiteService::limpiarTexto($d['serie'] ?? '');
        $ubic = TramiteService::limpiarTexto($d['ubicacion'] ?? '');
        if (mb_strlen($serie) > 120 || mb_strlen($ubic) > 190) {
            throw new FlujoException('La serie admite hasta 120 caracteres y la ubicación hasta 190.');
        }
        $desde = ! empty($d['fecha_desde']) ? (string) $d['fecha_desde'] : null;
        $hasta = ! empty($d['fecha_hasta']) ? (string) $d['fecha_hasta'] : null;
        if ($desde && $hasta && $desde > $hasta) {
            throw new FlujoException('La fecha inicial no puede ser posterior a la final.');
        }
        $unidad = ! empty($d['unidad_id']) ? (int) $d['unidad_id'] : null;
        if ($unidad && ! Unidad::query()->where('activo', true)->whereKey($unidad)->exists()) {
            throw new FlujoException('La unidad productora no es válida.');
        }
        $nd = (int) ($d['n_documentos'] ?? 0);
        $ni = (int) ($d['n_imagenes'] ?? 0);
        if ($nd < 0 || $ni < 0 || $nd > 10_000_000 || $ni > 100_000_000) {
            throw new FlujoException('La cantidad de documentos o imágenes es inválida.');
        }

        return ['titulo' => $titulo, 'descripcion' => $desc ?: null, 'tipo_soporte' => $soporte, 'serie' => $serie ?: null, 'ubicacion' => $ubic ?: null,
            'fecha_desde' => $desde, 'fecha_hasta' => $hasta, 'unidad_id' => $unidad, 'n_documentos' => $nd, 'n_imagenes' => $ni];
    }

    // ---------------------------------------------------------------- registro
    /** @param array<string,mixed> $d */
    public function crear(Usuario $u, array $d): Microforma
    {
        $this->exigir($u, 'archivo.gestionar', 'registrar microformas');
        $campos = $this->validarCampos($d);

        return DB::transaction(function () use ($u, $campos) {
            $m = Microforma::query()->create($campos + ['estado' => 'borrador', 'creado_por' => $u->id]);
            $m->forceFill(['codigo' => 'MF-'.now()->format('Y').'-'.str_pad((string) $m->id, 6, '0', STR_PAD_LEFT)])->save();
            $this->evento($m, 'registro', $u);
            $this->audit->registrar('archivo.crear', 'microforma', $m->id, ['codigo' => $m->codigo]);

            return $m->refresh();
        });
    }

    /** @param array<string,mixed> $d */
    public function actualizar(Microforma $m, Usuario $u, array $d): Microforma
    {
        $this->exigir($u, 'archivo.gestionar', 'editar microformas');
        $campos = $this->validarCampos($d);

        return DB::transaction(function () use ($m, $u, $campos) {
            $m = $this->bloquear($m);
            if (! $m->editable()) {
                throw new FlujoException('Solo se pueden editar microformas en borrador.');
            }
            $m->update($campos);
            $this->evento($m, 'edicion', $u);
            $this->audit->registrar('archivo.editar', 'microforma', $m->id);

            return $m->refresh();
        });
    }

    /** @param array<int,UploadedFile> $archivos */
    public function subirArchivos(Microforma $m, Usuario $u, array $archivos): int
    {
        $this->exigir($u, 'archivo.gestionar', 'cargar archivos');
        $archivos = array_values(array_filter($archivos));
        if ($archivos === []) {
            throw new FlujoException('Seleccione al menos un archivo.');
        }
        $creados = [];
        try {
            return DB::transaction(function () use ($m, $u, $archivos, &$creados) {
                $m = $this->bloquear($m);
                if (! $m->editable()) {
                    throw new FlujoException('Solo se pueden cargar archivos en microformas en borrador.');
                }
                $actual = $m->archivos()->count();
                if ($actual + count($archivos) > self::MAX_ARCHIVOS) {
                    throw new FlujoException('Máximo '.self::MAX_ARCHIVOS.' archivos por microforma.');
                }
                $orden = (int) DB::table('archivo_microforma_archivos')->where('microforma_id', $m->id)->max('orden');
                foreach ($archivos as $f) {
                    try {
                        $tipo = UploadValidator::validar($f);
                    } catch (\InvalidArgumentException $e) {
                        throw new FlujoException($f->getClientOriginalName().': '.$e->getMessage());
                    }
                    $a = $this->guardarArchivo($f, $tipo, 'Archivo maestro', $u, $creados);
                    DB::table('archivo_microforma_archivos')->insert(['microforma_id' => $m->id, 'archivo_id' => $a->id, 'orden' => ++$orden]);
                }
                $this->evento($m, 'carga_archivos', $u, ['cantidad' => count($archivos)]);
                $this->audit->registrar('archivo.cargar', 'microforma', $m->id, ['cantidad' => count($archivos)]);

                return count($archivos);
            });
        } catch (\Throwable $e) {
            foreach ($creados as $ruta) {
                $this->vault->delete($ruta); // sin archivos huérfanos si algo falla
            }
            throw $e;
        }
    }

    /** @param array{mime:string,ext:string} $tipo @param array<int,string> $creados */
    private function guardarArchivo(UploadedFile $f, array $tipo, string $titulo, Usuario $u, array &$creados): Archivo
    {
        $bytes = (string) file_get_contents($f->getRealPath());
        $r = $this->vault->put($bytes);
        $creados[] = $r['ruta'];
        $nombre = preg_replace('/[^\w.\- ]+/u', '_', basename($f->getClientOriginalName())) ?: 'archivo';
        $a = new Archivo(['ambito' => 'archivo', 'nombre_original' => mb_substr($nombre, 0, 190), 'mime' => $tipo['mime'], 'tamano' => $r['tamano'], 'sha256' => $r['sha256'],
            'ruta' => $r['ruta'], 'titulo' => $titulo, 'subido_por' => $u->run, 'nivel_acceso' => 'reservado']);
        $this->firmas->analizar($a, $bytes);
        $a->save();

        return $a;
    }

    public function quitarArchivo(Microforma $m, Usuario $u, int $archivoId): void
    {
        $this->exigir($u, 'archivo.gestionar', 'quitar archivos');
        DB::transaction(function () use ($m, $u, $archivoId) {
            $m = $this->bloquear($m);
            if (! $m->editable()) {
                throw new FlujoException('Solo se pueden quitar archivos de microformas en borrador.');
            }
            $a = $m->archivos()->where('archivos.id', $archivoId)->first(); // solo archivos de ESTA microforma
            if (! $a) {
                throw new FlujoException('El archivo no pertenece a esta microforma.');
            }
            DB::table('archivo_microforma_archivos')->where('microforma_id', $m->id)->where('archivo_id', $a->id)->delete();
            $ruta = $a->ruta;
            $nombre = $a->nombre_original;
            $sha = $a->sha256;
            $a->delete();
            $this->vault->delete($ruta);
            $this->evento($m, 'quita_archivo', $u, ['archivo' => $nombre, 'sha256' => $sha]);
            $this->audit->registrar('archivo.quitar', 'microforma', $m->id, ['sha256' => $sha]);
        });
    }

    /** @param array<string,mixed> $d */
    public function agregarItem(Microforma $m, Usuario $u, array $d): MicroformaItem
    {
        $this->exigir($u, 'archivo.gestionar', 'indexar documentos');
        $ref = TramiteService::limpiarTexto($d['referencia'] ?? '');
        $desc = TramiteService::limpiarTexto($d['descripcion'] ?? '');
        $fojas = TramiteService::limpiarTexto($d['fojas'] ?? '');
        $pos = TramiteService::limpiarTexto($d['posicion'] ?? '');
        if ($ref === '' || mb_strlen($ref) > 120 || mb_strlen($desc) > 255 || mb_strlen($fojas) > 30 || mb_strlen($pos) > 30) {
            throw new FlujoException('Referencia obligatoria (hasta 120 caracteres); descripción hasta 255; fojas y posición hasta 30.');
        }

        return DB::transaction(function () use ($m, $u, $ref, $desc, $fojas, $pos, $d) {
            $m = $this->bloquear($m);
            if (! $m->editable()) {
                throw new FlujoException('Solo se puede indexar en microformas en borrador.');
            }
            if ($m->items()->count() >= self::MAX_ITEMS) {
                throw new FlujoException('Máximo '.self::MAX_ITEMS.' documentos indexados por microforma.');
            }
            $fecha = ! empty($d['fecha_documento']) ? (string) $d['fecha_documento'] : null;
            $i = MicroformaItem::query()->create(['microforma_id' => $m->id, 'orden' => (int) $m->items()->max('orden') + 1, 'referencia' => $ref, 'descripcion' => $desc ?: null,
                'fojas' => $fojas ?: null, 'posicion' => $pos ?: null, 'fecha_documento' => $fecha]);
            $this->evento($m, 'indice', $u, ['referencia' => $ref]);

            return $i;
        });
    }

    public function quitarItem(Microforma $m, Usuario $u, int $itemId): void
    {
        $this->exigir($u, 'archivo.gestionar', 'modificar el índice');
        DB::transaction(function () use ($m, $u, $itemId) {
            $m = $this->bloquear($m);
            if (! $m->editable()) {
                throw new FlujoException('Solo se puede modificar el índice de microformas en borrador.');
            }
            $i = MicroformaItem::query()->where('microforma_id', $m->id)->find($itemId); // anti-IDOR: solo de esta microforma
            if (! $i) {
                throw new FlujoException('El documento indexado no existe en esta microforma.');
            }
            $ref = $i->referencia;
            $i->delete();
            $this->evento($m, 'indice_quita', $u, ['referencia' => $ref]);
        });
    }

    // ---------------------------------------------------------------- ciclo de vida
    public function enviarARevision(Microforma $m, Usuario $u): void
    {
        $this->exigir($u, 'archivo.gestionar', 'enviar a revisión');
        DB::transaction(function () use ($m, $u) {
            $m = $this->bloquear($m);
            if ($m->estado !== 'borrador') {
                throw new FlujoException('Solo se puede enviar a revisión una microforma en borrador.');
            }
            $n = $m->archivos()->count();
            if ($n < 1) {
                throw new FlujoException('Cargue al menos un archivo maestro antes de enviar a revisión.');
            }
            $m->forceFill(['estado' => 'en_revision', 'n_imagenes' => $m->n_imagenes ?: $n, 'sha256_conjunto' => $this->hashConjunto($m)])->save();
            $this->evento($m, 'envio_revision', $u, ['archivos' => $n]);
            $this->audit->registrar('archivo.revision', 'microforma', $m->id, ['archivos' => $n]);
        });
    }

    public function devolver(Microforma $m, Usuario $u, string $motivo): void
    {
        $this->exigir($u, 'archivo.certificar', 'devolver microformas a borrador');
        $motivo = TramiteService::limpiarTexto($motivo, true);
        if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 1000) {
            throw new FlujoException('Indique el motivo de la devolución (10 a 1000 caracteres).');
        }
        DB::transaction(function () use ($m, $u, $motivo) {
            $m = $this->bloquear($m);
            if ($m->estado !== 'en_revision') {
                throw new FlujoException('Solo se puede devolver una microforma en revisión.');
            }
            $m->forceFill(['estado' => 'borrador', 'sha256_conjunto' => null])->save();
            $this->evento($m, 'devolucion', $u, ['motivo' => $motivo]);
            $this->audit->registrar('archivo.devolver', 'microforma', $m->id, [], true);
        });
    }

    /** Certifica: acta numerada + archivo del acta; exige integridad y separación de funciones (quien registró no certifica). */
    public function certificar(Microforma $m, Usuario $u, string $actaNumero, ?UploadedFile $acta): void
    {
        $this->exigir($u, 'archivo.certificar', 'certificar microformas');
        $actaNumero = trim($actaNumero);
        if ($actaNumero === '' || mb_strlen($actaNumero) > 60 || ! preg_match('/^[\p{L}\p{N} .\/_\-]+$/u', $actaNumero)) {
            throw new FlujoException('Indique el número del acta (hasta 60 caracteres: letras, números, espacio, punto, guion o barra).');
        }
        if (! $acta) {
            throw new FlujoException('Adjunte el acta de certificación en PDF.');
        }
        try {
            $tipo = UploadValidator::validar($acta);
        } catch (\InvalidArgumentException $e) {
            throw new FlujoException('Acta: '.$e->getMessage());
        }
        if ($tipo['mime'] !== 'application/pdf') {
            throw new FlujoException('El acta debe ser un PDF.');
        }
        $creados = [];
        try {
            DB::transaction(function () use ($m, $u, $actaNumero, $acta, $tipo, &$creados) {
                $m = $this->bloquear($m);
                if ($m->estado !== 'en_revision') {
                    throw new FlujoException('Solo se puede certificar una microforma en revisión.');
                }
                if ((int) $m->creado_por === (int) $u->id) {
                    throw new FlujoException('Separación de funciones: quien registró la microforma no puede certificarla.');
                }
                $fallos = $this->archivosConProblemas($m);
                if ($fallos) {
                    throw new FlujoException('No se puede certificar: falló la verificación de integridad de '.count($fallos).' archivo(s).');
                }
                $hash = $this->hashConjunto($m);
                if ($m->sha256_conjunto !== null && ! hash_equals($m->sha256_conjunto, $hash)) {
                    throw new FlujoException('No se puede certificar: el conjunto de archivos cambió desde el envío a revisión.');
                }
                $a = $this->guardarArchivo($acta, $tipo, 'Acta de certificación '.$actaNumero, $u, $creados);
                $m->forceFill(['estado' => 'certificada', 'certificada_por' => $u->id, 'certificada_en' => now(), 'acta_numero' => $actaNumero, 'acta_archivo_id' => $a->id, 'sha256_conjunto' => $hash])->save();
                $this->evento($m, 'certificacion', $u, ['acta' => $actaNumero, 'sha256_conjunto' => $hash, 'firma_acta' => $a->firma_estado]);
                $this->audit->registrar('archivo.certificar', 'microforma', $m->id, ['acta' => $actaNumero, 'firma_acta' => $a->firma_estado], true);
            });
        } catch (\Throwable $e) {
            foreach ($creados as $ruta) {
                $this->vault->delete($ruta);
            }
            throw $e;
        }
    }

    public function anular(Microforma $m, Usuario $u, string $motivo): void
    {
        $this->exigir($u, 'archivo.gestionar', 'anular microformas');
        $motivo = TramiteService::limpiarTexto($motivo, true);
        if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 1000) {
            throw new FlujoException('Indique el motivo de la anulación (10 a 1000 caracteres).');
        }
        DB::transaction(function () use ($m, $u, $motivo) {
            $m = $this->bloquear($m);
            if (! in_array($m->estado, ['borrador', 'en_revision'], true)) {
                throw new FlujoException('Solo se pueden anular microformas en borrador o en revisión; una certificada no se anula.');
            }
            $m->forceFill(['estado' => 'anulada'])->save();
            $this->evento($m, 'anulacion', $u, ['motivo' => $motivo]);
            $this->audit->registrar('archivo.anular', 'microforma', $m->id, [], true);
        });
    }

    // ---------------------------------------------------------------- integridad y consulta
    /** @return array<int,string> nombres de archivos ilegibles o con huella distinta */
    private function archivosConProblemas(Microforma $m): array
    {
        $malos = [];
        foreach ($m->archivos()->get() as $a) {
            try {
                $this->vault->get($a->ruta, $a->sha256); // descifra y verifica el SHA-256
            } catch (\Throwable) {
                $malos[] = $a->nombre_original;
            }
        }
        if ($m->acta_archivo_id) {
            $acta = Archivo::query()->find($m->acta_archivo_id);
            try {
                if (! $acta) {
                    throw new \RuntimeException('sin acta');
                }
                $this->vault->get($acta->ruta, $acta->sha256);
            } catch (\Throwable) {
                $malos[] = 'Acta de certificación';
            }
        }

        return $malos;
    }

    /** @return array{ok:bool,archivos:int,problemas:array<int,string>} */
    public function verificar(Microforma $m, Usuario $u): array
    {
        $this->exigir($u, 'archivo.ver', 'verificar la integridad');
        $problemas = $this->archivosConProblemas($m);
        if ($m->estado === 'certificada' && $m->sha256_conjunto !== null && ! hash_equals($m->sha256_conjunto, $this->hashConjunto($m))) {
            $problemas[] = 'El conjunto de archivos no coincide con el hash fijado al certificar';
        }
        $r = ['ok' => $problemas === [], 'archivos' => $m->archivos()->count(), 'problemas' => $problemas];
        $this->evento($m, 'verificacion', $u, ['ok' => $r['ok'], 'problemas' => $problemas]);
        $this->audit->registrar('archivo.verificar', 'microforma', $m->id, ['ok' => $r['ok']], ! $r['ok']);

        return $r;
    }

    /** @return array{bytes:string,mime:string,nombre:string} */
    public function descargar(Microforma $m, Usuario $u, int $archivoId): array
    {
        $this->exigir($u, 'archivo.ver', 'consultar archivos');
        $a = $m->archivos()->where('archivos.id', $archivoId)->first();
        if (! $a && $m->acta_archivo_id === $archivoId) {
            $a = Archivo::query()->find($archivoId);
        }
        if (! $a) {
            throw new FlujoException('El archivo no pertenece a esta microforma.'); // anti-IDOR
        }
        try {
            $bytes = $this->vault->get($a->ruta, $a->sha256);
        } catch (\Throwable) {
            $this->evento($m, 'consulta_fallida', $u, ['archivo' => $a->nombre_original]);
            throw new FlujoException('No fue posible leer el archivo: falló la verificación de integridad. Avise al encargado del archivo.');
        }
        $this->evento($m, 'consulta', $u, ['archivo' => $a->nombre_original, 'sha256' => $a->sha256]);
        $this->audit->registrar('archivo.descarga', 'microforma', $m->id, ['sha256' => $a->sha256], true);

        return ['bytes' => $bytes, 'mime' => $a->mime, 'nombre' => $a->nombre_original];
    }

    // ---------------------------------------------------------------- disposición de originales (doble control)
    public function solicitarDisposicion(Microforma $m, Usuario $u, string $tipo, string $motivo): void
    {
        $this->exigir($u, 'archivo.gestionar', 'solicitar la disposición de originales');
        $motivo = TramiteService::limpiarTexto($motivo, true);
        if (! in_array($tipo, ['eliminar', 'transferir'], true)) {
            throw new FlujoException('Tipo de disposición inválido.');
        }
        if (mb_strlen($motivo) < 20 || mb_strlen($motivo) > 1500) {
            throw new FlujoException('Fundamente la solicitud (20 a 1500 caracteres, con la base legal o reglamentaria).');
        }
        DB::transaction(function () use ($m, $u, $tipo, $motivo) {
            $m = $this->bloquear($m);
            if ($m->estado !== 'certificada') {
                throw new FlujoException('Solo se puede disponer de los originales de una microforma certificada.');
            }
            if ($m->disposicion_estado !== 'no_aplica') {
                throw new FlujoException('Ya existe una solicitud de disposición para esta microforma.');
            }
            $m->forceFill(['disposicion' => $tipo, 'disposicion_estado' => 'pendiente', 'disposicion_motivo' => $motivo, 'disposicion_solicitada_por' => $u->id])->save();
            $this->evento($m, 'disposicion_solicitada', $u, ['tipo' => $tipo, 'motivo' => $motivo]);
            $this->audit->registrar('archivo.disposicion_solicitar', 'microforma', $m->id, ['tipo' => $tipo], true);
        });
    }

    public function resolverDisposicion(Microforma $m, Usuario $u, bool $autorizar, string $acta, string $motivo = ''): void
    {
        $this->exigir($u, 'archivo.disposicion', 'resolver solicitudes de disposición');
        $acta = trim($acta);
        $motivo = TramiteService::limpiarTexto($motivo, true);
        if ($autorizar && ($acta === '' || mb_strlen($acta) > 60 || ! preg_match('/^[\p{L}\p{N} .\/_\-]+$/u', $acta))) {
            throw new FlujoException('Indique el número del acto administrativo que autoriza (hasta 60 caracteres).');
        }
        if (! $autorizar && mb_strlen($motivo) < 10) {
            throw new FlujoException('Indique el motivo del rechazo (al menos 10 caracteres).');
        }
        DB::transaction(function () use ($m, $u, $autorizar, $acta, $motivo) {
            $m = $this->bloquear($m);
            if ($m->disposicion_estado !== 'pendiente') {
                throw new FlujoException('No hay una solicitud de disposición pendiente.');
            }
            if ((int) $m->disposicion_solicitada_por === (int) $u->id) {
                throw new FlujoException('Doble control: quien solicita la disposición no puede autorizarla ni rechazarla.');
            }
            if ($autorizar) {
                $m->forceFill(['disposicion_estado' => 'autorizada', 'disposicion_autorizada_por' => $u->id, 'disposicion_acta' => $acta])->save();
                $this->evento($m, 'disposicion_autorizada', $u, ['acta' => $acta]);
            } else {
                $m->forceFill(['disposicion' => 'conservar', 'disposicion_estado' => 'no_aplica', 'disposicion_motivo' => null, 'disposicion_solicitada_por' => null])->save();
                $this->evento($m, 'disposicion_rechazada', $u, ['motivo' => $motivo]);
            }
            $this->audit->registrar($autorizar ? 'archivo.disposicion_autorizar' : 'archivo.disposicion_rechazar', 'microforma', $m->id, [], true);
        });
    }

    public function registrarEjecucion(Microforma $m, Usuario $u): void
    {
        $this->exigir($u, 'archivo.gestionar', 'registrar la ejecución de la disposición');
        DB::transaction(function () use ($m, $u) {
            $m = $this->bloquear($m);
            if ($m->disposicion_estado !== 'autorizada') {
                throw new FlujoException('La disposición debe estar autorizada para registrar su ejecución.');
            }
            $m->forceFill(['disposicion_estado' => 'ejecutada', 'disposicion_en' => now()])->save();
            $this->evento($m, 'disposicion_ejecutada', $u, ['tipo' => $m->disposicion, 'acta' => $m->disposicion_acta]);
            $this->audit->registrar('archivo.disposicion_ejecutar', 'microforma', $m->id, ['tipo' => $m->disposicion], true);
        });
    }

    // ---------------------------------------------------------------- constancia
    public function constanciaPdf(Microforma $m, Usuario $u): string
    {
        $this->exigir($u, 'archivo.ver', 'emitir la constancia');
        $m->load('unidad', 'creador', 'certificador', 'items', 'eventos.usuario');
        $html = view('pdf.microforma', ['m' => $m, 'archivos' => $m->archivos()->get(), 'acta' => $m->acta_archivo_id ? Archivo::query()->find($m->acta_archivo_id) : null])->render();
        $o = new Options;
        $o->set('isRemoteEnabled', false);
        $o->set('isPhpEnabled', false);
        $o->set('isJavascriptEnabled', false);
        $o->set('chroot', [resource_path('views/pdf')]);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('fontCache', storage_path('framework/dompdf'));
        $o->set('tempDir', storage_path('framework/dompdf'));
        @mkdir(storage_path('framework/dompdf'), 0750, true);
        $d = new Dompdf($o);
        $d->loadHtml($html, 'UTF-8');
        $d->setPaper('letter');
        $d->render();
        $this->evento($m, 'constancia', $u);
        $this->audit->registrar('archivo.constancia', 'microforma', $m->id);

        return (string) $d->output();
    }
}
