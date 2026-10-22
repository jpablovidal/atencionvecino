<?php

namespace App\Models;

use App\Support\Run;
use Illuminate\Database\Eloquent\Model;

/**
 * Persona habilitada en DocDigital. El RUN no se guarda en claro (huella HMAC + máscara).
 *
 * @property int $usuario_id
 * @property string $nombre
 * @property int|null $entidad_id
 * @property string|null $roles
 */
class DocDigitalUsuario extends Model
{
    protected $table = 'docdigital_usuarios';

    protected $guarded = ['id'];

    protected $hidden = ['run_huella'];

    protected $casts = ['vigente' => 'boolean', 'sincronizado_en' => 'datetime'];

    public const ROLES = [1 => 'Tramitador(a)', 2 => 'Oficina de Partes (salida)', 3 => 'Oficina de Partes (entrada)', 4 => 'Administrador(a)'];

    /** @return list<int> */
    public function rolesLista(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->roles)), fn ($r) => isset(self::ROLES[$r])));
    }

    /** @return list<string> */
    public function rolesEtiquetas(): array
    {
        return array_map(fn ($r) => self::ROLES[$r], $this->rolesLista());
    }

    /** Huella del RUN (sin guion ni puntos, con dígito verificador) para cruzar con los usuarios locales sin guardar el RUN. */
    public static function huella(string $run): ?string
    {
        $n = Run::normalizar($run);
        if ($n === null && ctype_digit(trim($run))) { // la API describe el RUN «sin guion ni dígito verificador»
            $n = Run::normalizar(trim($run).Run::dv(trim($run)));
        }

        return $n === null ? null : hash_hmac('sha256', $n, (string) config('app.key'));
    }
}
