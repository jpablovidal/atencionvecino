<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Segundo factor del administrador: contraseña de un solo uso basada en tiempo (TOTP, RFC 6238: HMAC-SHA1, 30 s, 6 dígitos),
 * compatible con Google Authenticator, Microsoft Authenticator, FreeOTP y similares. El secreto se guarda cifrado con APP_KEY,
 * cada código se acepta una sola vez (anti-reutilización) y hay 8 códigos de respaldo de un solo uso, guardados con hash.
 */
class TotpService
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PASO = 30;

    private const VENTANA = 1; // ±30 s por desfase de reloj

    public function __construct(private readonly AuditoriaService $audit) {}

    /** ¿Debe este usuario usar segundo factor? Todo administrador del sistema, si la exigencia está activa. */
    public static function exigido(Usuario $u): bool
    {
        return (bool) config('sgd.admin_2fa') && $u->rolBase() === 'admin';
    }

    public function configurado(Usuario $u): bool
    {
        return $u->totp_secreto !== null && $u->totp_confirmado_en !== null;
    }

    public static function generarSecreto(): string
    {
        $b = random_bytes(20);
        $bits = '';
        foreach (str_split($b) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $o = '';
        foreach (str_split($bits, 5) as $g) {
            $o .= self::B32[bindec(str_pad($g, 5, '0'))];
        }

        return $o;
    }

    private static function decodificar(string $s): string
    {
        $bits = '';
        foreach (str_split(strtoupper($s)) as $c) {
            $p = strpos(self::B32, $c);
            if ($p === false) {
                continue;
            }
            $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
        }
        $o = '';
        foreach (str_split($bits, 8) as $g) {
            if (strlen($g) === 8) {
                $o .= chr(bindec($g));
            }
        }

        return $o;
    }

    public static function codigo(string $secreto, int $paso): string
    {
        $h = hash_hmac('sha1', pack('J', $paso), self::decodificar($secreto), true);
        $o = ord($h[19]) & 0x0F;
        $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);

        return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** @return int|null paso (ventana de 30 s) en que el código es válido, o null */
    public static function pasoValido(string $secreto, string $codigo, ?int $ahora = null, ?int $despuesDe = null): ?int
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';
        if (strlen($codigo) !== 6) {
            return null;
        }
        $p0 = intdiv($ahora ?? time(), self::PASO);
        for ($d = -self::VENTANA; $d <= self::VENTANA; $d++) {
            $paso = $p0 + $d;
            if (($despuesDe === null || $paso > $despuesDe) && hash_equals(self::codigo($secreto, $paso), $codigo)) {
                return $paso;
            }
        }

        return null;
    }

    public function urlOtpauth(Usuario $u, string $secreto): string
    {
        $emisor = 'SGD '.(string) config('sgd.institucion_nombre', 'Municipalidad');

        return 'otpauth://totp/'.rawurlencode($emisor.':'.$u->correo).'?secret='.$secreto.'&issuer='.rawurlencode($emisor).'&algorithm=SHA1&digits=6&period=30';
    }

    /** Inicia la inscripción: guarda un secreto sin confirmar y lo devuelve para mostrarlo una vez. */
    public function iniciarInscripcion(Usuario $u): string
    {
        if ($this->configurado($u)) {
            throw new FlujoException('El segundo factor ya está configurado.');
        }
        $s = self::generarSecreto();
        $u->forceFill(['totp_secreto' => Crypt::encryptString($s), 'totp_confirmado_en' => null, 'totp_ultimo_paso' => null])->save();

        return $s;
    }

    public function secretoPendiente(Usuario $u): ?string
    {
        if ($u->totp_secreto === null || $u->totp_confirmado_en !== null) {
            return null;
        }
        try {
            return Crypt::decryptString($u->totp_secreto);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Confirma la inscripción con un primer código y entrega los códigos de respaldo (única vez que se ven). @return list<string> */
    public function confirmarInscripcion(Usuario $u, string $codigo): array
    {
        $s = $this->secretoPendiente($u);
        $paso = $s ? self::pasoValido($s, $codigo) : null;
        if ($paso === null) {
            $this->audit->registrar('2fa_inscripcion_fallida', 'usuario', $u->id, []);
            throw new FlujoException('El código no es válido. Revise la hora de su teléfono e ingrese el código vigente.');
        }
        $resp = [];
        for ($i = 0; $i < 8; $i++) {
            $resp[] = strtoupper(bin2hex(random_bytes(4)));
        }
        $u->forceFill(['totp_confirmado_en' => now(), 'totp_ultimo_paso' => $paso, 'totp_respaldo' => json_encode(array_map(fn ($c) => $this->hashRespaldo($c), $resp))])->save();
        $this->audit->registrar('2fa_activado', 'usuario', $u->id, []);

        return $resp;
    }

    private function hashRespaldo(string $c): string
    {
        return hash_hmac('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c) ?? ''), (string) config('app.key'));
    }

    /** Verifica un código TOTP o de respaldo al ingresar. Consume el paso (o el código de respaldo). */
    public function verificar(Usuario $u, string $codigo): bool
    {
        if (! $this->configurado($u)) {
            return false;
        }

        return DB::transaction(function () use ($u, $codigo) {
            $f = Usuario::query()->lockForUpdate()->find($u->id);
            try {
                $s = Crypt::decryptString((string) $f->totp_secreto);
            } catch (\Throwable) {
                return false;
            }
            $paso = self::pasoValido($s, $codigo, null, $f->totp_ultimo_paso !== null ? (int) $f->totp_ultimo_paso : null);
            if ($paso !== null) {
                $f->forceFill(['totp_ultimo_paso' => $paso])->save();
                $this->audit->registrar('2fa_ok', 'usuario', $u->id, []);

                return true;
            }
            $h = $this->hashRespaldo($codigo);
            $lista = (array) json_decode((string) $f->totp_respaldo, true);
            $i = array_search($h, $lista, true);
            if ($i !== false && strlen(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '') === 8) {
                unset($lista[$i]);
                $f->forceFill(['totp_respaldo' => json_encode(array_values($lista))])->save();
                $this->audit->registrar('2fa_respaldo_usado', 'usuario', $u->id, ['restantes' => count($lista)]);

                return true;
            }
            $this->audit->registrar('2fa_fallido', 'usuario', $u->id, []);

            return false;
        });
    }

    /** Restablece el segundo factor de otro administrador (queda en auditoría; deberá inscribirse de nuevo). */
    public function restablecer(Usuario $admin, Usuario $objetivo): void
    {
        if ($admin->id === $objetivo->id) {
            throw new FlujoException('Para cambiar su propio segundo factor use la opción de su cuenta.');
        }
        $objetivo->forceFill(['totp_secreto' => null, 'totp_confirmado_en' => null, 'totp_ultimo_paso' => null, 'totp_respaldo' => null])->save();
        $this->audit->registrar('2fa_restablecido', 'usuario', $objetivo->id, ['por' => $admin->id]);
    }
}
