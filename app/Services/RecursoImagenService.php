<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\RecursoImagen;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;

/**
 * Biblioteca de imágenes del editor de documentos y plantillas. Las imágenes se validan por contenido, se re-codifican
 * (sin metadatos ni cargas incrustadas), se guardan cifradas en el repositorio y se sirven solo a usuarios autenticados.
 * En los PDF se incrustan como data URI.
 */
class RecursoImagenService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const MAX_LADO_ENTRADA = 4000;

    public const MAX_ANCHO = 1600;

    /** @var array<int,string> */
    private array $cacheUri = [];

    public function __construct(private readonly FileVault $vault, private readonly AuditoriaService $audit) {}

    public function subir(UploadedFile $f, Usuario $u): RecursoImagen
    {
        if (! $f->isValid() || $f->getSize() === 0 || $f->getSize() > self::MAX_BYTES) {
            throw new FlujoException('La imagen debe pesar hasta 2 MB.');
        }
        $bytes = (string) file_get_contents($f->getRealPath());
        $info = @getimagesizefromstring($bytes);
        $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_WEBP => 'webp'];
        if (! $info || ! isset($tipos[$info[2]])) {
            throw new FlujoException('Formato no permitido: use PNG, JPG o WebP (no SVG ni GIF).');
        }
        [$w, $h] = $info;
        if ($w < 8 || $h < 8 || $w > self::MAX_LADO_ENTRADA || $h > self::MAX_LADO_ENTRADA || $w * $h > 12_000_000) {
            throw new FlujoException('Dimensiones no permitidas (mínimo 8×8 px; máximo 4000×4000 px y 12 megapíxeles).');
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            throw new FlujoException('La imagen está dañada.');
        }
        if ($w > self::MAX_ANCHO) {
            $img = imagescale($img, self::MAX_ANCHO) ?: $img;
        }
        $jpeg = $tipos[$info[2]] === 'jpeg';
        imagesavealpha($img, true);
        ob_start();
        $jpeg ? imagejpeg($img, null, 88) : imagepng($img, null, 6);
        $salida = (string) ob_get_clean();
        $ancho = imagesx($img);
        $alto = imagesy($img);
        if ($salida === '') {
            throw new FlujoException('No se pudo procesar la imagen.');
        }
        $sha = hash('sha256', $salida);
        if ($existente = RecursoImagen::query()->where('sha256', $sha)->first()) {
            return $existente; // misma imagen ya cargada
        }
        $g = $this->vault->put($salida);
        $nombre = mb_substr(preg_replace('/[^\p{L}\p{N} ._\-]/u', '', pathinfo($f->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'imagen', 0, 100);
        $r = RecursoImagen::query()->create([
            'nombre' => $nombre, 'mime' => $jpeg ? 'image/jpeg' : 'image/png', 'ancho' => $ancho, 'alto' => $alto, 'tamano' => strlen($salida),
            'sha256' => $sha, 'ruta' => $g['ruta'], 'subido_por' => $u->id, 'created_at' => now(),
        ]);
        $this->audit->registrar('imagen.subir', 'recurso_imagen', $r->id, ['bytes' => strlen($salida)]);

        return $r;
    }

    public function url(RecursoImagen $r): string
    {
        return '/app/recursos/'.$r->id;
    }

    /** @return array{bytes:string,mime:string,sha256:string} */
    public function contenido(RecursoImagen $r): array
    {
        return ['bytes' => $this->vault->get($r->ruta, $r->sha256), 'mime' => $r->mime, 'sha256' => $r->sha256];
    }

    /** Reemplaza las referencias /app/recursos/N por data URI (para PDF). Una imagen inexistente o alterada se omite. */
    public function incrustar(string $html): string
    {
        return preg_replace_callback('#src="/app/recursos/([0-9]{1,9})"#', function ($m) {
            $id = (int) $m[1];
            if (! array_key_exists($id, $this->cacheUri)) {
                try {
                    $r = RecursoImagen::query()->find($id);
                    $c = $r ? $this->contenido($r) : null;
                    $this->cacheUri[$id] = $c ? 'data:'.$c['mime'].';base64,'.base64_encode($c['bytes']) : '';
                } catch (\Throwable) {
                    $this->cacheUri[$id] = '';
                }
            }

            return 'src="'.$this->cacheUri[$id].'"';
        }, $html) ?? $html;
    }
}
