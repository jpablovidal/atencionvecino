<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Usuario;
use App\Services\Admin\TotpService;
use App\Services\QrService;
use Illuminate\Http\Request;

/** Segundo factor (TOTP) del administrador: inscripción con QR, verificación al ingresar y códigos de respaldo. */
class DosFactoresController extends Controller
{
    public function __construct(private readonly TotpService $totp, private readonly QrService $qr) {}

    public function index(Request $r)
    {
        $u = $r->user();
        if (! TotpService::exigido($u) && ! $this->totp->configurado($u)) {
            return redirect()->route('app.bandeja');
        }
        if ($this->totp->configurado($u)) {
            if ((int) $r->session()->get('totp_ok') === (int) $u->id) {
                return redirect()->route('app.bandeja');
            }

            return view('app.dos_factores', ['modo' => 'verificar']);
        }
        $s = $this->totp->secretoPendiente($u) ?? $this->totp->iniciarInscripcion($u);

        return view('app.dos_factores', ['modo' => 'inscribir', 'secreto' => $s, 'qr' => $this->qr->dataUri($this->totp->urlOtpauth($u, $s))]);
    }

    public function verificar(Request $r)
    {
        $d = $r->validate(['codigo' => 'required|string|max:20']);
        $u = $r->user();
        try {
            if ($this->totp->configurado($u)) {
                if (! $this->totp->verificar($u, $d['codigo'])) {
                    return back()->with('error', 'El código no es válido o ya fue usado.');
                }
                $r->session()->put('totp_ok', $u->id);

                return redirect()->intended(route('app.bandeja'));
            }
            $resp = $this->totp->confirmarInscripcion($u, $d['codigo']);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }
        $r->session()->put('totp_ok', $u->id);

        return view('app.dos_factores', ['modo' => 'respaldo', 'respaldo' => $resp]);
    }

    public function restablecer(Request $r, int $usuario)
    {
        $obj = Usuario::query()->findOrFail($usuario);
        try {
            $this->totp->restablecer($r->user(), $obj);
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Segundo factor restablecido: deberá inscribirlo de nuevo al ingresar.');
    }
}
