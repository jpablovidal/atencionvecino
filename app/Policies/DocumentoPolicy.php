<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\Usuario;

class DocumentoPolicy
{
    public function ver(Usuario $u, Documento $d): bool
    {
        return $u->can('ver', $d->expediente);
    }

    public function operar(Usuario $u, Documento $d): bool
    {
        return $u->can('operar', $d->expediente);
    }
}
