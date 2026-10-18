<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\Usuario;
use App\Services\AccesoRestringidoService;

class DocumentoPolicy
{
    public function ver(Usuario $u, Documento $d): bool
    {
        return $u->can('ver', $d->expediente) && app(AccesoRestringidoService::class)->puedeVerDocumento($u, $d);
    }

    public function operar(Usuario $u, Documento $d): bool
    {
        return $u->can('operar', $d->expediente) && app(AccesoRestringidoService::class)->puedeVerDocumento($u, $d);
    }
}
