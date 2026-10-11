<?php

namespace App\Services;

use App\Models\DelegacionFirma;
use App\Models\TipoDocumento;
use App\Models\Usuario;

class DelegacionService
{
    /** ¿Puede esta persona firmar como "alcalde" este tipo de documento? Alcalde titular o delegación vigente. */
    public function puedeFirmarComoAlcalde(Usuario $u, TipoDocumento $tipo): bool
    {
        if (! $u->activo || ! $u->puede_firmar) {
            return false;
        }
        if ($u->rolBase() === 'alcalde') {
            return true;
        }
        $hoy = now('America/Santiago')->toDateString();

        return DelegacionFirma::query()->where('tipo_documento_id', $tipo->id)->where('delegado_id', $u->id)->where('activo', true)
            ->whereDate('vigente_desde', '<=', $hoy)->whereDate('vigente_hasta', '>=', $hoy)->exists();
    }
}
