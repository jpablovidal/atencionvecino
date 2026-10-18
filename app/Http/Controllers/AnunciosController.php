<?php

namespace App\Http\Controllers;

use App\Exceptions\FlujoException;
use App\Models\Anuncio;
use App\Services\AnuncioService;
use Illuminate\Http\Request;

/** Mantenedor de anuncios del modal de bienvenida y entrega pública de su imagen o video. */
class AnunciosController extends Controller
{
    public function __construct(private readonly AnuncioService $svc) {}

    private function reglas(): array
    {
        return ['titulo' => 'required|string|max:200', 'descripcion' => 'nullable|string|max:3000', 'audiencia' => 'required|in:vecinos,funcionarios,todos', 'medio' => 'nullable|file|max:'.(int) (AnuncioService::MAX_VIDEO / 1024),
            'medio_alt' => 'nullable|string|max:300', 'quitar_medio' => 'nullable|boolean', 'enlace_url' => 'nullable|string|max:400', 'enlace_texto' => 'nullable|string|max:100',
            'inicia_en' => 'nullable|date_format:Y-m-d', 'termina_en' => 'nullable|date_format:Y-m-d', 'activo' => 'nullable|boolean', 'orden' => 'nullable|integer|between:1,999'];
    }

    public function index()
    {
        return view('app.anuncios.index', ['anuncios' => Anuncio::query()->orderByDesc('activo')->orderBy('orden')->orderByDesc('id')->get(), 'audiencias' => AnuncioService::AUDIENCIAS]);
    }

    public function nuevo()
    {
        return view('app.anuncios.form', ['a' => null, 'audiencias' => AnuncioService::AUDIENCIAS]);
    }

    public function editar(Anuncio $anuncio)
    {
        return view('app.anuncios.form', ['a' => $anuncio, 'audiencias' => AnuncioService::AUDIENCIAS]);
    }

    public function guardar(Request $r, ?Anuncio $anuncio = null)
    {
        $d = $r->validate($this->reglas());
        $d['activo'] = $r->boolean('activo');
        try {
            $this->svc->guardar($r->user(), $d, $r->file('medio'), $anuncio, $r->boolean('quitar_medio'));
        } catch (FlujoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.anuncios')->with('ok', $anuncio ? 'Anuncio actualizado.' : 'Anuncio creado.');
    }

    public function alternar(Request $r, Anuncio $anuncio)
    {
        $this->svc->alternar($r->user(), $anuncio);

        return back()->with('ok', $anuncio->activo ? 'Anuncio activado.' : 'Anuncio desactivado.');
    }

    public function eliminar(Request $r, Anuncio $anuncio)
    {
        $this->svc->eliminar($r->user(), $anuncio);

        return redirect()->route('app.anuncios')->with('ok', 'Anuncio eliminado.');
    }

    /** Imagen o video del anuncio: público mientras el anuncio esté vigente; quien administra anuncios también ve los vigentes a futuro o vencidos (vista previa). */
    public function medio(Request $r, Anuncio $anuncio)
    {
        $admin = $r->user()?->tienePermiso('anuncios.gestionar');
        abort_unless($anuncio->medio_ruta && ($admin || $anuncio->vigente()), 404);
        try {
            $c = $this->svc->contenido($anuncio);
        } catch (\Throwable) {
            abort(404);
        }
        $tam = strlen($c['bytes']);
        $h = ['Content-Type' => $c['mime'], 'X-Content-Type-Options' => 'nosniff', 'Accept-Ranges' => 'bytes', 'Cache-Control' => 'public, max-age=3600', 'ETag' => '"'.$c['sha256'].'"',
            'Content-Disposition' => 'inline; filename="anuncio-'.$anuncio->id.'"'];
        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) $r->header('Range'), $m) && ($m[1] !== '' || $m[2] !== '')) {
            $ini = $m[1] === '' ? max(0, $tam - (int) $m[2]) : (int) $m[1];
            $fin = $m[1] === '' || $m[2] === '' ? $tam - 1 : min((int) $m[2], $tam - 1);
            if ($ini > $fin || $ini >= $tam) {
                return response('', 416, $h + ['Content-Range' => "bytes */$tam"]);
            }

            return response(substr($c['bytes'], $ini, $fin - $ini + 1), 206, $h + ['Content-Range' => "bytes $ini-$fin/$tam"]);
        }
        $resp = response($c['bytes'], 200, $h);
        $resp->isNotModified($r);

        return $resp;
    }
}
