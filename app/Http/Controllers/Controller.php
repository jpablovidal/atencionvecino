<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function permiso(string $permiso): void
    {
        abort_unless(request()->user()?->tienePermiso($permiso), 403);
    }
}
