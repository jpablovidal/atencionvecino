<?php

namespace App\Services\Repositorio;

use App\Exceptions\FlujoException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * Carga de la clave JSON de la cuenta de servicio de Drive desde el panel. Se valida su contenido y se guarda FUERA del docroot,
 * con permisos 600 y reemplazo atómico; la clave nunca se muestra (solo el correo de la cuenta y el proyecto).
 */
class CredencialDriveService
{
    public const MAX_BYTES = 20480;

    /** Destinos de la clave subida: archivo en la carpeta de secretos. */
    public const DESTINOS = ['drive' => 'drive-sa.json', 'gmail' => 'gmail-sa.json'];

    public const ARCHIVO = 'drive-sa.json';

    public static function directorio(): string
    {
        return rtrim((string) (config('sgd.secrets_path') ?: dirname(base_path()).'/sgd_secrets'), '/');
    }

    /** @return array{ruta:string,email:string,proyecto:string} */
    public function guardar(UploadedFile $f, string $destino = 'drive'): array
    {
        $nombre = self::DESTINOS[$destino] ?? throw new FlujoException('Destino de la clave desconocido.');
        if (! $f->isValid() || $f->getSize() === false || $f->getSize() > self::MAX_BYTES || $f->getSize() < 100) {
            throw new FlujoException('El archivo JSON no es válido o supera los 20 KB.');
        }
        $contenido = (string) file_get_contents($f->getRealPath());
        $sa = json_decode($contenido, true);
        if (! is_array($sa) || ($sa['type'] ?? null) !== 'service_account') {
            throw new FlujoException('El archivo no es la clave JSON de una cuenta de servicio de Google (type: service_account).');
        }
        $email = (string) ($sa['client_email'] ?? '');
        if (! preg_match('/^[A-Za-z0-9._\-]+@[A-Za-z0-9.\-]+\.iam\.gserviceaccount\.com$/', $email)) {
            throw new FlujoException('El correo de la cuenta de servicio (client_email) no es válido.');
        }
        $clave = (string) ($sa['private_key'] ?? '');
        if (! str_starts_with($clave, '-----BEGIN PRIVATE KEY-----') || openssl_pkey_get_private($clave) === false) {
            throw new FlujoException('La clave privada del archivo no es válida (¿descargó el JSON completo?).');
        }
        $proyecto = mb_substr((string) ($sa['project_id'] ?? ''), 0, 100);
        if ($proyecto === '') {
            throw new FlujoException('El archivo no indica el proyecto (project_id).');
        }

        $dir = self::directorio();
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true)) {
            throw new FlujoException('No se pudo crear la carpeta de secretos del servidor.');
        }
        $ruta = $dir.'/'.$nombre;
        $tmp = $ruta.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($tmp, $contenido, LOCK_EX) === false || ! @chmod($tmp, 0600) || ! @rename($tmp, $ruta)) {
            @unlink($tmp);
            throw new FlujoException('No se pudo guardar el archivo en el servidor.');
        }
        if ($destino === 'drive') {
            Cache::forget('drive_access_token'); // la próxima llamada usa la clave nueva
        }

        return ['ruta' => $ruta, 'email' => $email, 'proyecto' => $proyecto];
    }

    /** Resumen sin datos secretos del JSON configurado. @return array{email:string,proyecto:string,fecha:int}|null */
    public static function resumen(?string $ruta): ?array
    {
        if (! $ruta || ! is_file($ruta) || filesize($ruta) > self::MAX_BYTES) {
            return null;
        }
        $sa = json_decode((string) file_get_contents($ruta), true);
        if (! is_array($sa) || empty($sa['client_email'])) {
            return null;
        }

        return ['email' => (string) $sa['client_email'], 'proyecto' => (string) ($sa['project_id'] ?? ''), 'fecha' => (int) filemtime($ruta)];
    }

    /** Acepta el ID o la URL de Drive (…/folders/ID, …?id=ID) y devuelve solo el ID. */
    public static function extraerId(string $v): string
    {
        $v = trim($v);
        if (str_contains($v, 'drive.google.com') || str_contains($v, 'docs.google.com')) {
            if (preg_match('#/folders/([A-Za-z0-9_\-]{10,100})#', $v, $m) || preg_match('#[?&]id=([A-Za-z0-9_\-]{10,100})#', $v, $m)) {
                return $m[1];
            }
        }

        return $v;
    }
}
