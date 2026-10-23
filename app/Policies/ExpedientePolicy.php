<?php

namespace App\Policies;

use App\Models\Expediente;
use App\Models\Usuario;
use App\Services\SubroganciaService;

class ExpedientePolicy
{
    /** El admin NO lee expedientes; el auditor lee todo (solo lectura); los demás, su unidad o participación. */
    public function ver(Usuario $u, Expediente $e): bool
    {
        if (! $u->activo) {
            return false;
        }
        if ($u->veTodo()) {
            return true;
        }

        return $u->operaExpedientes() && ! $this->esSuyo($u, $e) && $this->participa($u, $e);
    }

    public function operar(Usuario $u, Expediente $e): bool
    {
        if ($u->activo && $u->esSuperadmin()) { // el superadministrador gestiona cualquier expediente (excepto el que él mismo solicitó como vecino); sus acciones quedan en la auditoría
            return ! $this->esSuyo($u, $e);
        }

        return $u->activo && $u->operaExpedientes() && ! $this->esSuyo($u, $e) && $this->participa($u, $e);
    }

    /** Conflicto de interés: quien solicitó el trámite como vecino(a) no lo tramita como funcionario(a) (lo ve en «Mis solicitudes»). */
    private function esSuyo(Usuario $u, Expediente $e): bool
    {
        return $e->solicitud !== null && ($e->solicitud->run === $u->run || $e->solicitud->interesado_run === $u->run); // también si la solicitud se hizo a su nombre
    }

    private function participa(Usuario $u, Expediente $e): bool
    {
        if ($u->unidad_id && $u->unidad_id === $e->unidad_id) {
            return true;
        }
        if ($this->aCargoDe($u->id, $e)) {
            return true;
        }
        // subrogancia vigente: lo que el titular tiene a su cargo (no toda su unidad)
        foreach (app(SubroganciaService::class)->idsTitulares($u) as $titularId) {
            if ($this->aCargoDe($titularId, $e)) {
                return true;
            }
        }

        return false;
    }

    /** Es responsable del expediente, se le derivó o tiene un paso de documento en él. */
    private function aCargoDe(int $usuarioId, Expediente $e): bool
    {
        if ($e->responsable_id === $usuarioId || $e->derivaciones()->where('a_usuario_id', $usuarioId)->exists()) {
            return true;
        }

        return $e->documentos()->whereHas('pasos', fn ($q) => $q->where('usuario_id', $usuarioId))->exists();
    }
}
