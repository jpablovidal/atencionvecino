<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\ConfigSitio;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\FileVault;
use App\Support\Ambiente;
use App\Support\AuditTriggers;
use App\Support\ProductionGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso del sistema de PRUEBAS a PRODUCCIÓN. Es irreversible y BORRA todos los procesos y expedientes creados en pruebas
 * (solicitudes, expedientes, documentos, archivos y sus versiones, pagos, respuestas, microformas, transparencia, colas) incluidos
 * los archivos cifrados del repositorio. Se CONSERVAN la configuración (usuarios reales, roles, unidades, áreas, trámites, tipos,
 * plantillas, firmantes, feriados, apariencia, integraciones) y la cadena de auditoría (evidencia). Reinicia los correlativos.
 * No se ejecuta si la configuración de producción no pasa el control de seguridad: así nunca deja el sitio inaccesible.
 */
class ProduccionService
{
    public const CLAVE = 'ambiente_produccion';

    public const FRASE = 'BORRAR DATOS DE PRUEBA Y PASAR A PRODUCCION';

    /** Tablas de PROCESOS que se vacían, en orden seguro respecto de las claves foráneas (hijas primero). */
    public const BORRAR = [
        'acceso_restringido', 'respuesta_final_items', 'respuestas_finales', 'avisos_movimiento', 'archivo_eventos', 'archivo_microforma_archivos', 'archivo_microforma_items',
        'archivo_microformas', 'egresos', 'decretos_pago', 'expediente_vinculos', 'documento_version_eventos', 'documento_pasos', 'documento_versiones', 'archivo_versiones',
        'archivos', 'comentarios', 'derivaciones', 'notificaciones', 'pisee_consultas', 'comunicaciones_oficiales', 'devoluciones', 'transparencia_derivaciones',
        'transparencia_requerimientos', 'transparencia_activa_items', 'transparencia_activa_registros', 'solicitud_movimientos', 'documentos', 'expedientes', 'solicitudes',
        'correlativos', 'cuentas_prueba', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
    ];

    /** Configuración y evidencia que se CONSERVA (cada tabla nueva debe clasificarse en BORRAR o aquí: lo verifica una prueba). */
    public const CONSERVAR = [
        'anuncios', 'api_config', 'areas', 'audit_log', 'config_sitio', 'conexiones_resumen', 'delegaciones_firma', 'firma_estilos', 'firma_posiciones', 'geo_dns_cache',
        'incidentes', 'instituciones', 'menu_items', 'metricas_recursos', 'migrations', 'pago_firmantes', 'pisee_servicios', 'plantilla_unidades', 'plantilla_versiones',
        'plantillas', 'procesos_codificador', 'recursos_imagen', 'repositorio_carpetas', 'repositorio_reubicaciones', 'rol_permisos', 'roles', 'sessions', 'tipos_documento',
        'tramite_anexos', 'tramite_unidad', 'tramite_versiones', 'tramites', 'transparencia_feriados', 'unidades', 'usuario_areas', 'usuarios', 'sqlite_sequence',
    ];

    /** Correo de los usuarios de demostración del proyecto: se desactivan al pasar a producción. */
    public const DOMINIO_DEMO = '.invalid';

    public function __construct(private readonly AuditoriaService $audit, private readonly TotpService $totp, private readonly FileVault $vault) {}

    /** ¿El sistema ya fue pasado a producción desde el panel? */
    public static function enProduccion(): bool
    {
        try {
            $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE)->value('valor'), true);
        } catch (\Throwable) {
            return false;
        }

        return is_array($j) && ($j['ambiente'] ?? null) === 'produccion';
    }

    /** @return array<string,mixed>|null */
    public static function registro(): ?array
    {
        try {
            $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE)->value('valor'), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($j) ? $j : null;
    }

    /** @return array<string,int> filas a borrar por tabla (solo las que tienen datos) */
    public function conteos(): array
    {
        $o = [];
        foreach (self::BORRAR as $t) {
            if (in_array($t, ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'correlativos', 'cuentas_prueba'], true) || ! Schema::hasTable($t)) {
                continue;
            }
            $n = DB::table($t)->count();
            if ($n > 0) {
                $o[$t] = $n;
            }
        }

        return $o;
    }

    /** @return list<array{clave:string,etiqueta:string,ok:bool,detalle:string}> requisitos para pasar a producción */
    public function requisitos(Usuario $admin, bool $totpVerificado): array
    {
        $r = [];
        $add = function (string $clave, string $etiqueta, bool $ok, string $detalle) use (&$r) {
            $r[] = compact('clave', 'etiqueta', 'ok', 'detalle');
        };
        $add('ambiente', 'El sistema está en ambiente de pruebas', Ambiente::esPruebas() && ! self::enProduccion(), 'Solo se puede pasar de pruebas a producción, una vez.');
        $add('2fa', 'Su cuenta de administrador tiene segundo factor activo y verificado en esta sesión', $this->totp->configurado($admin) && $totpVerificado, 'Configure el segundo factor en «Segundo factor» e ingrese de nuevo.');
        $reales = Usuario::query()->where('activo', true)->where('correo', 'not like', '%'.self::DOMINIO_DEMO)->get()->filter(fn ($u) => $u->rolBase() === 'admin');
        $add('admin_real', 'Existe al menos un administrador real (no de demostración) con segundo factor', $reales->contains(fn ($u) => $this->totp->configurado($u)),
            'Cree un administrador real con «php artisan sgd:crear-admin» e inscriba su segundo factor; las cuentas de demostración se desactivan.');
        foreach (ProductionGuard::errores(false) as $i => $e) {
            $add('guardia_'.$i, 'Configuración de producción: '.$e, false, 'Corrija el archivo .env del servidor (guía de instalación) y recargue esta página.');
        }
        if (! ProductionGuard::errores(false)) {
            $add('guardia', 'La configuración del servidor cumple las exigencias de producción (ClaveÚnica y FirmaGob reales, correo real, HTTPS, sin depuración)', true, '');
        }

        return $r;
    }

    public function puedePasar(Usuario $admin, bool $totpVerificado): bool
    {
        return collect($this->requisitos($admin, $totpVerificado))->every(fn ($x) => $x['ok']);
    }

    /**
     * @return array{borrado:array<string,int>,archivos_borrados:int,archivos_fallidos:int,usuarios_demo_desactivados:int}
     */
    public function pasarAProduccion(Usuario $admin, string $frase, string $codigoTotp, bool $totpVerificado, bool $confirmaRespaldo): array
    {
        if (! $admin->tienePermiso('produccion.gestionar')) {
            throw new FlujoException('No tiene permiso para pasar el sistema a producción.');
        }
        if (trim($frase) !== self::FRASE) {
            throw new FlujoException('Escriba exactamente la frase de confirmación: '.self::FRASE);
        }
        if (! $confirmaRespaldo) {
            throw new FlujoException('Confirme que respaldó la base de datos y los archivos si desea conservar una copia de los datos de prueba.');
        }
        if (! $this->puedePasar($admin, $totpVerificado)) {
            throw new FlujoException('Aún no se cumplen todos los requisitos para pasar a producción.');
        }
        if (! $this->totp->verificar($admin, $codigoTotp)) {
            throw new FlujoException('El código del segundo factor no es válido o ya fue usado: espere el siguiente código e inténtelo de nuevo.');
        }
        $cerrojo = Cache::lock('pasar-a-produccion', 600);
        if (! $cerrojo->get()) {
            throw new FlujoException('Ya hay un paso a producción en curso.');
        }
        try {
            $this->audit->registrar('produccion.inicio', 'sistema', 'ambiente', ['conteos' => $this->conteos()], true);
            $rutas = $this->rutasDeArchivos();
            $borrado = [];
            $demo = 0;
            AuditTriggers::sinBloqueoDelete('documento_versiones', fn () => DB::transaction(function () use (&$borrado, &$demo, $admin) {
                DB::table('solicitudes')->update(['expediente_id' => null]);
                DB::table('expedientes')->update(['comunicacion_id' => null]);
                foreach (self::BORRAR as $t) {
                    if (! Schema::hasTable($t)) {
                        continue;
                    }
                    $n = DB::table($t)->delete();
                    if ($n > 0 && ! in_array($t, ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true)) {
                        $borrado[$t] = $n;
                    }
                }
                DB::table('repositorio_reubicaciones')->delete();
                $demo = Usuario::query()->where('correo', 'like', '%'.self::DOMINIO_DEMO)->where('id', '!=', $admin->id)->update(['activo' => false]);
                DB::table('sessions')->where('user_id', '!=', $admin->id)->orWhereNull('user_id')->delete();
                ConfigSitio::query()->updateOrCreate(['clave' => self::CLAVE], ['valor' => json_encode(['ambiente' => 'produccion', 'en' => now()->toIso8601String(), 'por_run' => $admin->run,
                    'borrado' => $borrado, 'usuarios_demo_desactivados' => $demo]), 'actualizado_por' => $admin->id]);
            }));
            $this->reiniciarSecuencias();
            [$ok, $fallidos] = $this->borrarBlobs($rutas);
            Ambiente::aplicarOverride();
            $this->audit->registrar('produccion.completado', 'sistema', 'ambiente', ['borrado' => $borrado, 'archivos_borrados' => $ok, 'archivos_fallidos' => $fallidos, 'usuarios_demo_desactivados' => $demo], true);

            return ['borrado' => $borrado, 'archivos_borrados' => $ok, 'archivos_fallidos' => $fallidos, 'usuarios_demo_desactivados' => $demo];
        } finally {
            $cerrojo->release();
        }
    }

    /** @return list<string> */
    private function rutasDeArchivos(): array
    {
        $r = [];
        foreach ([['archivos', 'ruta'], ['archivo_versiones', 'ruta'], ['documentos', 'pdf_ruta'], ['documentos', 'pdf_firmado_ruta'], ['documento_versiones', 'pdf_ruta']] as [$t, $c]) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, $c)) {
                foreach (DB::table($t)->whereNotNull($c)->pluck($c) as $v) {
                    $r[(string) $v] = true;
                }
            }
        }

        return array_keys($r);
    }

    /** @param list<string> $rutas @return array{0:int,1:int} */
    private function borrarBlobs(array $rutas): array
    {
        $ok = 0;
        $fallidos = 0;
        foreach ($rutas as $ruta) {
            try {
                $this->vault->delete($ruta);
                $ok++;
            } catch (\Throwable) {
                $fallidos++;
            }
        }

        return [$ok, $fallidos];
    }

    private function reiniciarSecuencias(): void
    {
        $driver = DB::getDriverName();
        foreach (self::BORRAR as $t) {
            if (! Schema::hasTable($t) || in_array($t, ['cache', 'cache_locks', 'correlativos'], true)) {
                continue;
            }
            try {
                if ($driver === 'sqlite') {
                    DB::table('sqlite_sequence')->where('name', $t)->delete();
                } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                    DB::statement('ALTER TABLE `'.$t.'` AUTO_INCREMENT = 1');
                }
            } catch (\Throwable) {
                // la numeración interna no es crítica: el código del expediente sigue siendo único
            }
        }
    }
}
