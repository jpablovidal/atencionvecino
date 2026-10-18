<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\AccesoRestringido;
use App\Models\Archivo;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Expedientes, documentos y archivos restringidos (protección de datos personales: necesidad de conocer).
 * Quien no está autorizado no ve el contenido ni los nombres; cada acceso permitido o intento denegado queda en la bitácora de auditoría.
 * El administrador del sistema NUNCA accede al contenido (solo ve la bitácora); el responsable del expediente siempre está autorizado.
 */
class AccesoRestringidoService
{
    public function __construct(private readonly AuditoriaService $audit) {}

    // ---------------------------------------------------------------- decisión de acceso
    public function restringidoArchivo(Archivo $a): bool
    {
        return $a->restringido || (bool) $a->expediente_id && (bool) Expediente::query()->whereKey($a->expediente_id)->value('restringido');
    }

    public function restringidoDocumento(Documento $d): bool
    {
        return $d->restringido || (bool) Expediente::query()->whereKey($d->expediente_id)->value('restringido');
    }

    /** ¿Está el usuario autorizado para el expediente completo o para el elemento indicado? */
    public function autorizado(Usuario $u, Expediente $e, ?Archivo $a = null, ?Documento $d = null): bool
    {
        if (! $u->activo || $u->rolBase() === 'admin') {
            return false;
        }
        if ($e->responsable_id === $u->id) {
            return true;
        }

        return AccesoRestringido::query()->vigentes()->where('usuario_id', $u->id)->where('expediente_id', $e->id)
            ->where(function ($q) use ($a, $d) {
                $q->where(fn ($w) => $w->whereNull('archivo_id')->whereNull('documento_id'));
                if ($a) {
                    $q->orWhere('archivo_id', $a->id);
                }
                if ($d) {
                    $q->orWhere('documento_id', $d->id);
                }
            })->exists();
    }

    public function puedeVerArchivo(Usuario $u, Archivo $a): bool
    {
        if (! $this->restringidoArchivo($a)) {
            return true;
        }
        $e = $a->expediente_id ? Expediente::query()->find($a->expediente_id) : null;

        return $e !== null && $this->autorizado($u, $e, $a);
    }

    public function puedeVerDocumento(Usuario $u, Documento $d): bool
    {
        if (! $this->restringidoDocumento($d)) {
            return true;
        }
        $e = Expediente::query()->find($d->expediente_id);

        return $e !== null && $this->autorizado($u, $e, null, $d);
    }

    /** Verifica el acceso a un archivo restringido y lo registra; sin autorización aborta con 403 (mensaje genérico, sin revelar el nombre). */
    public function exigirArchivo(Usuario $u, Archivo $a, string $accion): void
    {
        if (! $this->restringidoArchivo($a)) {
            return;
        }
        $ok = $this->puedeVerArchivo($u, $a);
        $this->registrar($ok, $accion, 'archivo', $a->id, $a->expediente_id);
        abort_unless($ok, 403, 'Este archivo es restringido: no tiene autorización para verlo.');
    }

    public function exigirDocumento(Usuario $u, Documento $d, string $accion): void
    {
        if (! $this->restringidoDocumento($d)) {
            return;
        }
        $ok = $this->puedeVerDocumento($u, $d);
        $this->registrar($ok, $accion, 'documento', $d->id, $d->expediente_id);
        abort_unless($ok, 403, 'Este documento es restringido: no tiene autorización para verlo.');
    }

    private function registrar(bool $ok, string $accion, string $entidad, int $id, ?int $expedienteId): void
    {
        $this->audit->registrar($ok ? 'restringido.acceso' : 'restringido.intento', $entidad, $id, ['accion' => $accion, 'resultado' => $ok ? 'permitido' : 'denegado', 'expediente_id' => $expedienteId], true);
    }

    /**
     * Elementos de un expediente que el usuario NO puede ver.
     *
     * @return array{archivos:array<int,int>,documentos:array<int,int>,restringidos_archivos:array<int,int>,restringidos_documentos:array<int,int>}
     */
    public function ocultos(Usuario $u, Expediente $e): array
    {
        $out = ['archivos' => [], 'documentos' => [], 'restringidos_archivos' => [], 'restringidos_documentos' => []];
        foreach ($e->archivos()->get(['id', 'expediente_id', 'restringido']) as $a) {
            if ($this->restringidoArchivo($a)) {
                $out['restringidos_archivos'][] = $a->id;
                if (! $this->autorizado($u, $e, $a)) {
                    $out['archivos'][] = $a->id;
                }
            }
        }
        foreach ($e->documentos()->get(['id', 'expediente_id', 'restringido']) as $d) {
            if ($this->restringidoDocumento($d)) {
                $out['restringidos_documentos'][] = $d->id;
                if (! $this->autorizado($u, $e, null, $d)) {
                    $out['documentos'][] = $d->id;
                }
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- gestión
    private function motivo(string $m): string
    {
        $m = TramiteService::limpiarTexto($m);
        if (mb_strlen($m) < 10 || mb_strlen($m) > 300) {
            throw new FlujoException('Indique el motivo (entre 10 y 300 caracteres).');
        }

        return $m;
    }

    private function gestor(Usuario $u, Expediente $e): void
    {
        if ($u->rolBase() === 'admin' || ! $u->tienePermiso('restringidos.gestionar') || ! $u->can('ver', $e)) {
            throw new FlujoException('No tiene permiso para administrar restricciones en este expediente.');
        }
    }

    /** Solo quien ya está autorizado puede cambiar o levantar una restricción vigente (evita que un tercero se abra el acceso). */
    private function exigirAutorizado(Usuario $u, Expediente $e, ?Archivo $a = null, ?Documento $d = null): void
    {
        $restringido = $e->restringido || ($a && $a->restringido) || ($d && $d->restringido);
        if ($restringido && ! $this->autorizado($u, $e, $a, $d)) {
            throw new FlujoException('Solo una persona autorizada puede cambiar esta restricción.');
        }
    }

    private function darAcceso(Usuario $actor, Expediente $e, Usuario $para, string $motivo, ?Archivo $a = null, ?Documento $d = null, ?Carbon $vence = null): AccesoRestringido
    {
        return AccesoRestringido::query()->create(['expediente_id' => $e->id, 'archivo_id' => $a?->id, 'documento_id' => $d?->id, 'usuario_id' => $para->id, 'otorgado_por' => $actor->id, 'motivo' => $motivo, 'vence_en' => $vence]);
    }

    /** Marca o levanta la restricción de un expediente, archivo o documento. */
    public function restringir(Usuario $u, Expediente $e, string $motivo, ?Archivo $a = null, ?Documento $d = null): void
    {
        $this->gestor($u, $e);
        $this->exigirAutorizado($u, $e, $a, $d);
        $obj = $a ?? $d ?? $e;
        if ($a && $a->expediente_id !== $e->id || $d && $d->expediente_id !== $e->id) {
            throw new FlujoException('El elemento no pertenece al expediente.');
        }
        if ($obj->restringido) {
            throw new FlujoException('Ya está restringido.');
        }
        $motivo = $this->motivo($motivo);
        DB::transaction(function () use ($u, $e, $a, $d, $obj, $motivo) {
            $obj->forceFill(['restringido' => true, 'restringido_motivo' => $motivo, 'restringido_por' => $u->id, 'restringido_en' => now()])->save();
            if (! $this->autorizado($u, $e, $a, $d)) { // quien aplica la restricción conserva su acceso
                $this->darAcceso($u, $e, $u, 'Persona que aplicó la restricción', $a, $d);
            }
            $this->audit->registrar('restringido.marcar', $a ? 'archivo' : ($d ? 'documento' : 'expediente'), $obj->id, ['expediente_id' => $e->id], true);
        });
    }

    public function liberar(Usuario $u, Expediente $e, ?Archivo $a = null, ?Documento $d = null): void
    {
        $this->gestor($u, $e);
        $obj = $a ?? $d ?? $e;
        if (! $obj->restringido) {
            throw new FlujoException('No está restringido.');
        }
        $this->exigirAutorizado($u, $e, $a, $d);
        $obj->forceFill(['restringido' => false, 'restringido_motivo' => null, 'restringido_por' => null, 'restringido_en' => null])->save();
        $this->audit->registrar('restringido.liberar', $a ? 'archivo' : ($d ? 'documento' : 'expediente'), $obj->id, ['expediente_id' => $e->id], true);
    }

    public function autorizar(Usuario $u, Expediente $e, int $usuarioId, string $motivo, ?string $vence = null, ?Archivo $a = null, ?Documento $d = null): AccesoRestringido
    {
        $this->gestor($u, $e);
        if (! ($e->restringido || ($a && $a->restringido) || ($d && $d->restringido))) {
            throw new FlujoException('Primero restrinja el expediente o el elemento.');
        }
        $this->exigirAutorizado($u, $e, $a, $d);
        $para = Usuario::query()->where('activo', true)->find($usuarioId) ?? throw new FlujoException('La persona no existe o está inactiva.');
        if ($para->rolBase() === 'admin' || ! ($para->operaExpedientes() || $para->rolBase() === 'auditor')) {
            throw new FlujoException('Solo pueden autorizarse funcionarios, alcalde, secretarios o auditoría; el administrador del sistema no accede al contenido.');
        }
        $motivo = $this->motivo($motivo);
        $v = null;
        if ($vence !== null && $vence !== '') {
            try {
                $v = Carbon::createFromFormat('Y-m-d', $vence, 'America/Santiago')->endOfDay();
            } catch (\Throwable) {
                throw new FlujoException('Fecha de vencimiento inválida.');
            }
            if ($v->isPast() || $v->gt(now()->addYear())) {
                throw new FlujoException('El vencimiento debe ser futuro y no superar un año.');
            }
        }
        $ya = AccesoRestringido::query()->vigentes()->where('expediente_id', $e->id)->where('usuario_id', $para->id)->where('archivo_id', $a?->id)->where('documento_id', $d?->id)->exists();
        if ($ya) {
            throw new FlujoException('La persona ya tiene una autorización vigente para esto.');
        }
        $r = $this->darAcceso($u, $e, $para, $motivo, $a, $d, $v);
        $this->audit->registrar('restringido.autorizar', 'expediente', $e->id, ['persona' => $para->id, 'archivo_id' => $a?->id, 'documento_id' => $d?->id, 'vence' => $v?->toDateString()], true);

        return $r;
    }

    public function revocar(Usuario $u, AccesoRestringido $r): void
    {
        $e = Expediente::query()->findOrFail($r->expediente_id);
        $this->gestor($u, $e);
        $a = $r->archivo_id ? Archivo::query()->find($r->archivo_id) : null;
        $d = $r->documento_id ? Documento::query()->find($r->documento_id) : null;
        $this->exigirAutorizado($u, $e, $a, $d);
        if (! $r->vigente()) {
            throw new FlujoException('La autorización ya no está vigente.');
        }
        $quedan = AccesoRestringido::query()->vigentes()->where('expediente_id', $e->id)->where('archivo_id', $r->archivo_id)->where('documento_id', $r->documento_id)->where('id', '!=', $r->id)->count()
            + ($e->responsable_id && $e->responsable_id !== $r->usuario_id ? 1 : 0);
        if ($quedan < 1) {
            throw new FlujoException('Debe quedar al menos una persona autorizada.');
        }
        $r->forceFill(['revocado_en' => now(), 'revocado_por' => $u->id])->save();
        $this->audit->registrar('restringido.revocar', 'expediente', $e->id, ['persona' => $r->usuario_id, 'archivo_id' => $r->archivo_id, 'documento_id' => $r->documento_id], true);
    }
}
