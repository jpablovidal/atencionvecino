<?php

namespace App\Console\Commands;

use App\Models\CuentaPrueba;
use App\Models\Rol;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Support\Ambiente;
use App\Support\Permisos;
use App\Support\Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Crea (solo si faltan) la unidad, los roles y las 2 cuentas de PRUEBA del archivo municipal en una instalación ya existente. */
class PruebasArchivo extends Command
{
    protected $signature = 'pruebas:archivo';

    protected $description = 'Crea unidad «Archivo Municipal», roles y 2 cuentas de prueba del módulo de microformas (solo SGD_AMBIENTE=pruebas; no toca las demás cuentas).';

    public function handle(): int
    {
        if (! Ambiente::esPruebas()) {
            $this->error('SGD_AMBIENTE no es «pruebas»: en producción cree la unidad, los roles y los usuarios desde la Administración.');

            return self::FAILURE;
        }
        $unidad = Unidad::query()->firstOrCreate(['codigo' => 'ARCH'], ['nombre' => 'Archivo Municipal', 'activo' => true]);
        $secmun = Unidad::query()->where('codigo', 'SECMUN')->first();
        $roles = [
            ['arch_gestion', 'Encargado(a) de archivo', 'Registra microformas, carga sus archivos e índice y gestiona la disposición de originales.', 'funcionario', ['archivo.ver', 'archivo.gestionar']],
            ['arch_cert', 'Ministro(a) de fe del archivo', 'Certifica microformas (acta) y autoriza la disposición de originales.', 'secretario', ['archivo.ver', 'archivo.certificar', 'archivo.disposicion']],
        ];
        foreach ($roles as [$clave, $nombre, $desc, $base, $extra]) {
            if (Rol::query()->where('clave', $clave)->exists()) {
                continue;
            }
            $rol = Rol::query()->create(['clave' => $clave, 'nombre' => $nombre, 'descripcion' => $desc, 'rol_base' => $base, 'sistema' => false, 'activo' => true]);
            foreach (array_unique([...Permisos::deBase($base), ...$extra]) as $p) {
                DB::table('rol_permisos')->insert(['rol_id' => $rol->id, 'permiso' => $p]);
            }
            $this->info("Rol creado: $nombre ($clave)");
        }
        $filas = [];
        foreach ([[10000011, 'Ximena', 'Archivista Municipal', 'arch_gestion', $unidad->id, 'Encargada del Archivo Municipal'], [10000012, 'Tomás', 'Ministro de Fe', 'arch_cert', $secmun?->id, 'Ministro de fe (Secretaría Municipal)']] as [$cuerpo, $nom, $ape, $rol, $uid, $cargo]) {
            $run = $cuerpo.'-'.Run::dv((string) $cuerpo);
            if (Usuario::query()->where('run', $run)->exists()) {
                $this->line("Ya existe el usuario $run: sin cambios.");

                continue;
            }
            Usuario::query()->create(['run' => $run, 'nombres' => $nom, 'apellidos' => $ape, 'correo' => strtolower($nom).'.'.strtolower(explode(' ', $ape)[0]).'@demo.santodomingo.invalid', 'cargo' => $cargo,
                'rol' => $rol, 'unidad_id' => $uid, 'puede_firmar' => false, 'activo' => true]);
            $pw = $this->generar();
            CuentaPrueba::query()->updateOrCreate(['run' => $run], ['tipo' => 'funcionario', 'nombres' => $nom, 'apellidos' => $ape, 'password' => Hash::make($pw), 'activo' => true]);
            $filas[] = [$run, $nom.' '.$ape, $rol, $pw];
        }
        if ($filas) {
            $this->table(['RUN (usuario)', 'Nombre', 'Rol', 'Contraseña (se muestra una vez)'], $filas);
        }

        return self::SUCCESS;
    }

    private function generar(): string
    {
        $a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $s = '';
        for ($i = 0; $i < 14; $i++) {
            $s .= $a[random_int(0, strlen($a) - 1)];
        }

        return $s.'-'.random_int(10, 99);
    }
}
