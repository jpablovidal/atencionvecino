<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

/** Guarda en BD solo el SHA-256 del identificador de sesión: un volcado de la tabla no permite secuestrar sesiones. */
class HashedDatabaseSessionHandler extends DatabaseSessionHandler
{
    private function h(string $id): string
    {
        return hash('sha256', $id);
    }

    public function read($sessionId): string|false
    {
        return parent::read($this->h($sessionId));
    }

    public function write($sessionId, $data): bool
    {
        return parent::write($this->h($sessionId), $data);
    }

    public function destroy($sessionId): bool
    {
        return parent::destroy($this->h($sessionId));
    }
}
