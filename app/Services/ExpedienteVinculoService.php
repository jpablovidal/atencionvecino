<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\AccesoRestringido;
use App\Models\Archivo;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\ExpedienteVinculo;
use App\Models\Microforma;
use App\Models\Usuario;
use App\Services\Firma\ArchivoFirmaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Asociar a un expediente otros expedientes o microformas para reutilizar procesos y antecedentes. El vínculo es una referencia;
 * además, el funcionario puede INCORPORAR copias de su contenido (documentos vigentes y archivos), que quedan en el expediente con su
 * origen y el SHA-256 original. Solo se ve y se copia lo que la persona ya puede ver: lo restringido exige autorización (y la copia
 * queda restringida también); las microformas exigen el permiso del archivo municipal y dejan cadena de custodia.
 */
class ExpedienteVinculoService
{
    public const MAX_INCORPORAR = 50;

    /** Archivos de otro expediente que pueden copiarse (no consultas PISEE ni comprobantes de ingreso). */
    public const AMBITOS = ['expediente', 'docdigital', 'anexo', 'respuesta'];

    public function __construct(
        private readonly AuditoriaService $audit, private readonly FileVault $vault, private readonly ArchivoFirmaService $firmas,
        private readonly AccesoRestringidoService $restringidos, private readonly DocumentoService $docs, private readonly ArchivoMunicipalService $micro,
    ) {}

    private function exigirOperar(Usuario $u, Expediente $destino): void
    {
        if (! $u->tienePermiso('expedientes.vincular') || ! $u->can('operar', $destino)) {
            throw new FlujoException('No tiene permiso para asociar contenido a este expediente.');
        }
        if ($destino->estado !== 'abierto') {
            throw new FlujoException('El expediente está cerrado: reábralo para asociar contenido.');
        }
    }

    private function motivo(string $m): string
    {
        $m = mb_substr(TramiteService::limpiarTexto($m), 0, 300);
        if (mb_strlen($m) < 10) {
            throw new FlujoException('Indique el motivo de la asociación (mínimo 10 caracteres).');
        }

        return $m;
    }

    /** Convierte la negativa de acceso (403 + registro del intento) en un mensaje claro para quien opera. */
    private function autorizado(callable $exigir): void
    {
        try {
            $exigir();
        } catch (HttpException) {
            throw new FlujoException('Hay contenido restringido para el que no está autorizado(a): no puede copiarlo. El intento queda registrado.');
        }
    }

    // ---------------------------------------------------------------- búsqueda
    /** Expedientes que la persona puede ver (misma regla que la ficha del expediente). @return Collection<int,Expediente> */
    public function buscarExpedientes(Usuario $u, Expediente $destino, string $q): Collection
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return collect();
        }
        $like = '%'.addcslashes($q, '%_\\').'%';
        $ya = ExpedienteVinculo::query()->where('expediente_id', $destino->id)->where('tipo', 'expediente')->pluck('origen_expediente_id');
        $cand = Expediente::query()->with('solicitud.tramite:id,nombre', 'unidad:id,nombre')->where('id', '!=', $destino->id)->whereNotIn('id', $ya)
            ->where(fn ($w) => $w->where('codigo', 'like', $like)
                ->orWhereHas('solicitud', fn ($s) => $s->where('folio', 'like', $like)->orWhereHas('tramite', fn ($t) => $t->where('nombre', 'like', $like)))
                ->orWhereHas('documentos', fn ($d) => $d->where('titulo', 'like', $like))
                ->orWhereHas('archivos', fn ($a) => $a->where('titulo', 'like', $like)->orWhere('nombre_original', 'like', $like)))
            ->orderByDesc('id')->limit(120)->get();

        return $cand->filter(fn ($e) => $u->can('ver', $e))->take(15)->values();
    }

    /** Microformas (no anuladas): requiere el permiso de consulta del archivo municipal. @return Collection<int,Microforma> */
    public function buscarMicroformas(Usuario $u, Expediente $destino, string $q): Collection
    {
        $q = trim($q);
        if (! $u->tienePermiso('archivo.ver') || mb_strlen($q) < 3) {
            return collect();
        }
        $like = '%'.addcslashes($q, '%_\\').'%';
        $ya = ExpedienteVinculo::query()->where('expediente_id', $destino->id)->where('tipo', 'microforma')->pluck('microforma_id');

        return Microforma::query()->with('unidad:id,nombre')->where('estado', '!=', 'anulada')->whereNotIn('id', $ya)
            ->where(fn ($w) => $w->where('codigo', 'like', $like)->orWhere('titulo', 'like', $like)->orWhere('serie', 'like', $like)->orWhere('descripcion', 'like', $like))
            ->orderByDesc('id')->limit(15)->get();
    }

    // ---------------------------------------------------------------- vínculos
    public function vincularExpediente(Usuario $u, Expediente $destino, int $origenId, string $motivo): ExpedienteVinculo
    {
        $this->exigirOperar($u, $destino);
        $motivo = $this->motivo($motivo);
        $origen = Expediente::query()->find($origenId);
        if (! $origen || $origen->id === $destino->id || ! $u->can('ver', $origen)) {
            throw new FlujoException('El expediente no existe o no puede asociarlo.');
        }
        if (ExpedienteVinculo::query()->where('expediente_id', $destino->id)->where('tipo', 'expediente')->where('origen_expediente_id', $origen->id)->exists()) {
            throw new FlujoException('Ese expediente ya está asociado.');
        }
        $v = ExpedienteVinculo::query()->create(['expediente_id' => $destino->id, 'tipo' => 'expediente', 'origen_expediente_id' => $origen->id, 'motivo' => $motivo, 'creado_por' => $u->id]);
        $this->audit->registrar('expediente.vincular', 'expediente', $destino->id, ['tipo' => 'expediente', 'origen' => $origen->id]);

        return $v;
    }

    public function vincularMicroforma(Usuario $u, Expediente $destino, int $microformaId, string $motivo): ExpedienteVinculo
    {
        $this->exigirOperar($u, $destino);
        if (! $u->tienePermiso('archivo.ver')) {
            throw new FlujoException('Asociar microformas requiere el permiso de consulta del archivo municipal.');
        }
        $motivo = $this->motivo($motivo);
        $m = Microforma::query()->where('estado', '!=', 'anulada')->find($microformaId) ?? throw new FlujoException('La microforma no existe o está anulada.');
        if (ExpedienteVinculo::query()->where('expediente_id', $destino->id)->where('tipo', 'microforma')->where('microforma_id', $m->id)->exists()) {
            throw new FlujoException('Esa microforma ya está asociada.');
        }
        $v = ExpedienteVinculo::query()->create(['expediente_id' => $destino->id, 'tipo' => 'microforma', 'microforma_id' => $m->id, 'motivo' => $motivo, 'creado_por' => $u->id]);
        $this->audit->registrar('expediente.vincular', 'expediente', $destino->id, ['tipo' => 'microforma', 'microforma' => $m->id]);

        return $v;
    }

    public function desvincular(Usuario $u, ExpedienteVinculo $v): void
    {
        $this->exigirOperar($u, $v->expediente);
        $v->delete(); // las copias ya incorporadas se conservan en el expediente (historia del trámite)
        $this->audit->registrar('expediente.desvincular', 'expediente', $v->expediente_id, ['tipo' => $v->tipo, 'origen' => $v->origen_expediente_id, 'microforma' => $v->microforma_id]);
    }

    // ---------------------------------------------------------------- contenido
    /**
     * Contenido asociado visible para la persona. Lo restringido sin autorización se informa como tal, sin título.
     *
     * @return array{tipo:string,titulo:string,documentos:array<int,array<string,mixed>>,archivos:array<int,array<string,mixed>>,acceso:bool}
     */
    public function contenido(Usuario $u, ExpedienteVinculo $v): array
    {
        if ($v->tipo === 'microforma') {
            $m = $v->microforma;
            if (! $m || ! $u->tienePermiso('archivo.ver')) {
                return ['tipo' => 'microforma', 'titulo' => $m?->titulo ?? 'Microforma', 'documentos' => [], 'archivos' => [], 'acceso' => false];
            }

            return ['tipo' => 'microforma', 'titulo' => trim(($m->codigo ? $m->codigo.' · ' : '').$m->titulo), 'documentos' => [], 'acceso' => true,
                'archivos' => $m->archivos->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->titulo ?: $a->nombre_original, 'oculto' => false, 'restringido' => false])->all()];
        }
        $o = $v->origen;
        if (! $o || ! $u->can('ver', $o)) {
            return ['tipo' => 'expediente', 'titulo' => $o?->codigo ?? 'Expediente', 'documentos' => [], 'archivos' => [], 'acceso' => false];
        }
        $ocultos = $this->restringidos->ocultos($u, $o);
        $docs = $o->documentos()->where('estado', 'vigente')->get()->map(fn ($d) => in_array($d->id, $ocultos['documentos'], true)
            ? ['id' => $d->id, 'nombre' => 'Documento restringido', 'oculto' => true, 'restringido' => true]
            : ['id' => $d->id, 'nombre' => trim($d->numeroFormateado().' — '.$d->titulo), 'oculto' => false, 'restringido' => in_array($d->id, $ocultos['restringidos_documentos'], true)]);
        $arch = $o->archivos()->whereIn('ambito', self::AMBITOS)->get()->map(fn ($a) => in_array($a->id, $ocultos['archivos'], true)
            ? ['id' => $a->id, 'nombre' => 'Archivo restringido', 'oculto' => true, 'restringido' => true]
            : ['id' => $a->id, 'nombre' => $a->titulo ?: $a->nombre_original, 'oculto' => false, 'restringido' => in_array($a->id, $ocultos['restringidos_archivos'], true)]);

        return ['tipo' => 'expediente', 'titulo' => $o->codigo, 'documentos' => $docs->all(), 'archivos' => $arch->all(), 'acceso' => true];
    }

    // ---------------------------------------------------------------- incorporar copias
    /**
     * @param  array<int,int>  $documentoIds
     * @param  array<int,int>  $archivoIds
     * @return int cantidad de archivos incorporados
     */
    public function incorporar(Usuario $u, ExpedienteVinculo $v, array $documentoIds, array $archivoIds): int
    {
        $destino = $v->expediente;
        $this->exigirOperar($u, $destino);
        $documentoIds = array_values(array_unique(array_map('intval', $documentoIds)));
        $archivoIds = array_values(array_unique(array_map('intval', $archivoIds)));
        if (! $documentoIds && ! $archivoIds) {
            throw new FlujoException('Seleccione al menos un documento o archivo.');
        }
        if (count($documentoIds) + count($archivoIds) > self::MAX_INCORPORAR) {
            throw new FlujoException('Máximo '.self::MAX_INCORPORAR.' elementos por vez.');
        }
        $copias = []; // [bytes, nombre, mime, titulo, descripcion, sensible, restringido]
        if ($v->tipo === 'microforma') {
            $m = $v->microforma ?? throw new FlujoException('La microforma ya no existe.');
            if ($documentoIds) {
                throw new FlujoException('Las microformas solo tienen archivos.');
            }
            foreach ($archivoIds as $id) {
                $r = $this->micro->descargar($m, $u, $id); // exige archivo.ver, valida pertenencia e integridad y deja cadena de custodia
                $copias[] = [$r['bytes'], $r['nombre'], $r['mime'], 'Copia de microforma: '.$r['nombre'], 'Microforma '.($m->codigo ?: '#'.$m->id).' — '.$m->titulo, true, false];
            }
        } else {
            $o = $v->origen ?? throw new FlujoException('El expediente ya no existe.');
            if (! $u->can('ver', $o)) {
                throw new FlujoException('Ya no puede ver el expediente asociado.');
            }
            $docs = Documento::query()->where('expediente_id', $o->id)->whereIn('id', $documentoIds)->where('estado', 'vigente')->get();
            $arch = Archivo::query()->where('expediente_id', $o->id)->whereIn('id', $archivoIds)->whereIn('ambito', self::AMBITOS)->get();
            if ($docs->count() !== count($documentoIds) || $arch->count() !== count($archivoIds)) {
                throw new FlujoException('Seleccione solo documentos vigentes y archivos del expediente asociado.');
            }
            foreach ($docs as $d) {
                $this->autorizado(fn () => $this->restringidos->exigirDocumento($u, $d, 'incorporar'));
                $copias[] = [$this->docs->leerPdf($d), 'documento-'.$d->numero.'-'.$d->anio.'.pdf', 'application/pdf', 'Copia: '.$d->numeroFormateado().' — '.$d->titulo,
                    'Documento '.$d->numeroFormateado().' del expediente '.$o->codigo, (bool) $d->sensible, $this->restringidos->restringidoDocumento($d)];
            }
            foreach ($arch as $a) {
                $this->autorizado(fn () => $this->restringidos->exigirArchivo($u, $a, 'incorporar'));
                $copias[] = [$this->vault->get($a->ruta, $a->sha256), $a->nombre_original, $a->mime, 'Copia: '.($a->titulo ?: $a->nombre_original), 'Archivo del expediente '.$o->codigo, (bool) $a->sensible, $this->restringidos->restringidoArchivo($a)];
            }
        }
        $creados = [];
        try {
            DB::transaction(function () use ($u, $v, $destino, $copias, &$creados) {
                foreach ($copias as [$bytes, $nombre, $mime, $titulo, $desc, $sensible, $restringido]) {
                    $f = $this->vault->put($bytes);
                    $creados[] = $f['ruta'];
                    $a = new Archivo(['expediente_id' => $destino->id, 'ambito' => 'expediente', 'nombre_original' => mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($nombre)) ?: 'archivo', 0, 190), 'mime' => $mime,
                        'tamano' => $f['tamano'], 'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => mb_substr($titulo, 0, 190), 'nivel_acceso' => 'reservado', 'sensible' => $sensible,
                        'subido_por' => $u->run, 'vinculo_id' => $v->id, 'origen_descripcion' => mb_substr($desc, 0, 200), 'origen_sha256' => $f['sha256']]);
                    $this->firmas->analizar($a, $bytes);
                    $a->save();
                    if ($restringido) { // la copia hereda la restricción y queda autorizada quien la incorporó
                        $a->forceFill(['restringido' => true, 'restringido_motivo' => 'Copia de contenido restringido', 'restringido_por' => $u->id, 'restringido_en' => now()])->save();
                        AccesoRestringido::query()->create(['expediente_id' => $destino->id, 'archivo_id' => $a->id, 'usuario_id' => $u->id, 'otorgado_por' => $u->id, 'motivo' => 'Quien incorporó la copia']);
                    }
                }
                $this->audit->registrar('expediente.incorporar', 'expediente', $destino->id, ['vinculo' => $v->id, 'tipo' => $v->tipo, 'archivos' => count($copias)], true);
            });
        } catch (\Throwable $e) {
            foreach ($creados as $r) {
                $this->vault->delete($r);
            }
            throw $e;
        }

        return count($copias);
    }
}
