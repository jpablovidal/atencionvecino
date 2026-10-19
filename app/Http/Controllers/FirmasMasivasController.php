<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Services\FirmaMasivaService;
use Illuminate\Http\Request;

/** «Mis firmas»: documentos que le toca firmar o visar, para resolverlos uno a uno o por lote. */
class FirmasMasivasController extends Controller
{
    public function __construct(private readonly FirmaMasivaService $svc) {}

    public function index(Request $r)
    {
        abort_unless($r->user()->operaExpedientes(), 403);
        $u = $r->user();

        return view('app.firmas', ['firmas' => $this->svc->pendientes($u, 'firma'), 'visaciones' => $this->svc->pendientes($u, 'visacion'), 'lote' => FirmaMasivaService::loteMax(), 'puedeFirmar' => $u->puede_firmar,
            'resultado' => session('resultado_lote')]);
    }

    public function firmar(Request $r)
    {
        $d = $r->validate(['pasos' => 'nullable|array|max:200', 'pasos.*' => 'integer', 'todos' => 'nullable|boolean', 'tipo_firma' => 'required|in:avanzada,simple', 'otp' => 'nullable|string|regex:/^\d{6}$/', 'reviso' => 'nullable|boolean', 'confirmo' => 'nullable|boolean']);
        if ($d['tipo_firma'] === 'simple' && (! $r->boolean('confirmo') || (time() - (int) $r->session()->get('login_at', 0)) > 1800)) {
            return back()->with('error', 'Para la firma electrónica simple confirme su voluntad de firmar y haya ingresado al sistema en los últimos 30 minutos (vuelva a ingresar si es necesario).');
        }
        try {
            $res = $this->svc->firmar($r->user(), $d['pasos'] ?? [], $r->boolean('todos'), $d['tipo_firma'], $d['otp'] ?? null, $r->boolean('reviso'));
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->volver($res, 'firmados');
    }

    public function visar(Request $r)
    {
        $d = $r->validate(['pasos' => 'nullable|array|max:200', 'pasos.*' => 'integer', 'todos' => 'nullable|boolean', 'comentario' => 'nullable|string|max:1200', 'reviso' => 'nullable|boolean']);
        try {
            $res = $this->svc->visar($r->user(), $d['pasos'] ?? [], $r->boolean('todos'), $d['comentario'] ?? null, $r->boolean('reviso'));
        } catch (FlujoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->volver($res, 'visados');
    }

    /** @param array<int,array{ok:bool,documento:string,mensaje:string}> $res */
    private function volver(array $res, string $verbo)
    {
        $ok = collect($res)->where('ok', true)->count();
        $mal = count($res) - $ok;

        return redirect()->route('app.firmas')->with($mal ? 'aviso' : 'ok', "Se $verbo: $ok de ".count($res).($mal ? " ($mal con observaciones: revise el detalle)." : '.'))->with('resultado_lote', $res);
    }
}
