<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Anuncio;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Mantenedor de anuncios del modal de bienvenida. El texto es plano (sin HTML). La imagen se valida por contenido y se re-codifica;
 * el video (MP4 o WebM) se valida por su firma. Los medios se guardan cifrados en el repositorio y se sirven por una ruta propia.
 */
class AnuncioService
{
    public const AUDIENCIAS = ['vecinos' => 'Vecinos(as) y público', 'funcionarios' => 'Funcionarios(as)', 'todos' => 'Todos'];

    public const MAX_IMAGEN = 2 * 1024 * 1024;

    public const MAX_VIDEO = 20 * 1024 * 1024;

    public function __construct(private readonly FileVault $vault, private readonly AuditoriaService $audit) {}

    /** @param array<string,mixed> $d */
    public function guardar(Usuario $u, array $d, ?UploadedFile $medio, ?Anuncio $a = null, bool $quitarMedio = false): Anuncio
    {
        $titulo = TramiteService::limpiarTexto((string) ($d['titulo'] ?? ''));
        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 160) {
            throw new FlujoException('El título debe tener entre 3 y 160 caracteres.');
        }
        $desc = TramiteService::limpiarTexto((string) ($d['descripcion'] ?? ''), true);
        if (mb_strlen($desc) > 1500) {
            throw new FlujoException('La descripción admite hasta 1.500 caracteres.');
        }
        $aud = (string) ($d['audiencia'] ?? 'vecinos');
        if (! isset(self::AUDIENCIAS[$aud])) {
            throw new FlujoException('Audiencia inválida.');
        }
        [$enlace, $texto] = $this->enlace((string) ($d['enlace_url'] ?? ''), (string) ($d['enlace_texto'] ?? ''));
        $ini = $this->fecha($d['inicia_en'] ?? null, false);
        $fin = $this->fecha($d['termina_en'] ?? null, true);
        if ($ini && $fin && $fin->lt($ini)) {
            throw new FlujoException('La fecha de término no puede ser anterior a la de inicio.');
        }
        $campos = ['titulo' => $titulo, 'descripcion' => $desc !== '' ? $desc : null, 'audiencia' => $aud, 'enlace_url' => $enlace, 'enlace_texto' => $texto,
            'inicia_en' => $ini?->format('Y-m-d H:i:s'), 'termina_en' => $fin?->format('Y-m-d H:i:s'), 'activo' => ! empty($d['activo']), 'orden' => max(1, min(999, (int) ($d['orden'] ?? 10)))];
        $alt = TramiteService::limpiarTexto((string) ($d['medio_alt'] ?? ''));
        $viejo = $a?->medio_ruta;
        if ($medio) {
            $campos += $this->procesar($medio);
        } elseif ($quitarMedio) {
            $campos += ['medio_tipo' => null, 'medio_mime' => null, 'medio_ruta' => null, 'medio_sha256' => null, 'medio_tamano' => null];
        }
        $tieneMedio = $medio || (! $quitarMedio && $a?->medio_tipo);
        if ($tieneMedio && mb_strlen($alt) < 5) {
            throw new FlujoException('Describa la imagen o el video (texto alternativo, mínimo 5 caracteres) para que lo entiendan las personas que usan lector de pantalla.');
        }
        $campos['medio_alt'] = $tieneMedio ? mb_substr($alt, 0, 200) : null;
        if ($a) {
            $a->forceFill($campos)->save();
        } else {
            $a = Anuncio::query()->create($campos + ['creado_por' => $u->id]);
        }
        if ($viejo && $viejo !== $a->medio_ruta) {
            $this->borrar($viejo);
        }
        $this->audit->registrar($a->wasRecentlyCreated ? 'anuncio.crear' : 'anuncio.editar', 'anuncio', $a->id, ['audiencia' => $aud, 'medio' => $a->medio_tipo], false);

        return $a;
    }

    public function eliminar(Usuario $u, Anuncio $a): void
    {
        $ruta = $a->medio_ruta;
        $id = $a->id;
        $a->delete();
        if ($ruta) {
            $this->borrar($ruta);
        }
        $this->audit->registrar('anuncio.eliminar', 'anuncio', $id, [], false);
    }

    public function alternar(Usuario $u, Anuncio $a): void
    {
        $a->forceFill(['activo' => ! $a->activo])->save();
        $this->audit->registrar($a->activo ? 'anuncio.activar' : 'anuncio.desactivar', 'anuncio', $a->id, [], false);
    }

    /** @return array{bytes:string,mime:string,sha256:string} */
    public function contenido(Anuncio $a): array
    {
        if (! $a->medio_ruta) {
            throw new FlujoException('Sin medio.');
        }

        return ['bytes' => $this->vault->get($a->medio_ruta, (string) $a->medio_sha256), 'mime' => (string) $a->medio_mime, 'sha256' => (string) $a->medio_sha256];
    }

    private function borrar(string $ruta): void
    {
        try {
            $this->vault->delete($ruta);
        } catch (\Throwable) {
            // el huérfano no afecta el funcionamiento; queda para la limpieza del repositorio
        }
    }

    /** @return array{0:?string,1:?string} */
    private function enlace(string $url, string $texto): array
    {
        $url = trim($url);
        if ($url === '') {
            return [null, null];
        }
        if (! (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) && ! preg_match('#^https://[^\s<>"\']+$#i', $url)) {
            throw new FlujoException('El enlace debe ser una ruta interna (/…) o una dirección https://.');
        }
        if (mb_strlen($url) > 300) {
            throw new FlujoException('El enlace es demasiado largo.');
        }
        $texto = mb_substr(TramiteService::limpiarTexto($texto), 0, 60);

        return [$url, $texto !== '' ? $texto : 'Más información'];
    }

    private function fecha(mixed $v, bool $finDelDia): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            $c = Carbon::createFromFormat('Y-m-d', (string) $v, 'America/Santiago');
        } catch (\Throwable) {
            throw new FlujoException('Fecha inválida.');
        }
        if (! $c || $c->format('Y-m-d') !== (string) $v) {
            throw new FlujoException('Fecha inválida.');
        }

        return ($finDelDia ? $c->endOfDay() : $c->startOfDay())->setTimezone(config('app.timezone'));
    }

    /** @return array<string,mixed> */
    private function procesar(UploadedFile $f): array
    {
        if (! $f->isValid() || $f->getSize() === 0) {
            throw new FlujoException('El archivo no se pudo cargar (revise su tamaño: el servidor limita las subidas).');
        }
        $cabecera = (string) file_get_contents($f->getRealPath(), false, null, 0, 16);
        $esMp4 = strlen($cabecera) >= 12 && substr($cabecera, 4, 4) === 'ftyp';
        $esWebm = str_starts_with($cabecera, "\x1A\x45\xDF\xA3");
        if ($esMp4 || $esWebm) {
            if ($f->getSize() > self::MAX_VIDEO) {
                throw new FlujoException('El video debe pesar hasta 20 MB.');
            }
            $bytes = (string) file_get_contents($f->getRealPath());
            $mime = $esMp4 ? 'video/mp4' : 'video/webm';
            $tipo = 'video';
        } else {
            if ($f->getSize() > self::MAX_IMAGEN) {
                throw new FlujoException('La imagen debe pesar hasta 2 MB.');
            }
            [$bytes, $mime] = $this->imagen((string) file_get_contents($f->getRealPath()));
            $tipo = 'imagen';
        }
        $g = $this->vault->put($bytes);

        return ['medio_tipo' => $tipo, 'medio_mime' => $mime, 'medio_ruta' => $g['ruta'], 'medio_sha256' => $g['sha256'], 'medio_tamano' => strlen($bytes)];
    }

    /** @return array{0:string,1:string} */
    private function imagen(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);
        $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg'];
        if (! $info || ! isset($tipos[$info[2]])) {
            throw new FlujoException('Formato no permitido: imagen PNG o JPG, o video MP4 o WebM (no SVG ni GIF).');
        }
        [$w, $h] = $info;
        if ($w < 8 || $h < 8 || $w > 4000 || $h > 4000 || $w * $h > 12_000_000) {
            throw new FlujoException('Dimensiones no permitidas (mínimo 8×8 px; máximo 4000×4000 px y 12 megapíxeles).');
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            throw new FlujoException('La imagen está dañada.');
        }
        if ($w > 1600) {
            $img = imagescale($img, 1600) ?: $img;
        }
        $jpeg = $tipos[$info[2]] === 'jpeg';
        imagesavealpha($img, true);
        ob_start();
        $jpeg ? imagejpeg($img, null, 88) : imagepng($img, null, 6);
        $salida = (string) ob_get_clean();
        if ($salida === '') {
            throw new FlujoException('No se pudo procesar la imagen.');
        }

        return [$salida, $jpeg ? 'image/jpeg' : 'image/png'];
    }
}
