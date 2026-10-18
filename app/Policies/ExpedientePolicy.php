<?php

namespace App\Policies;

use App\Models\Expediente;
use App\Models\Usuario;

class ExpedientePolicy
{
    /** El admin NO lee expedientes; el auditor lee todo (solo lectura); los demás, su unidad o participación. */
    public function ver(Usuario $u, Expediente $e): bool
    {
        if (! $u->activo) {
            return false;
        }
        if ($u->rolBase() === 'auditor') {
            return true;
        }

        return $u->operaExpedientes() && ! $this->esSuyo($u, $e) && $this->participa($u, $e);
    }

    public function operar(Usuario $u, Expediente $e): bool
    {
        return $u->activo && $u->operaExpedientes() && ! $this->esSuyo($u, $e) && $this->participa($u, $e);
    }

    /** Conflicto de interés: quien solicitó el trámite como vecino(a) no lo tramita como funcionario(a) (lo ve en «Mis solicitudes»). */
    private function esSuyo(Usuario $u, Expediente $e): bool
    {
        return $e->solicitud !== null && $e->solicitud->run === $u->run;
    }

    private function participa(Usuario $u, Expediente $e): bool
    {
        if ($u->unidad_id && $u->unidad_id === $e->unidad_id) {
            return true;
        }
        if ($e->responsable_id === $u->id || $e->derivaciones()->where('a_usuario_id', $u->id)->exists()) {
            return true;
        }

        return $e->documentos()->whereHas('pasos', fn ($q) => $q->where('usuario_id', $u->id))->exists();
    }
}
