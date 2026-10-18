<?php

namespace App\Console\Commands;

use App\Models\CuentaPrueba;
use App\Models\PagoFirmante;
use App\Models\Rol;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\PagoConfigService;
use App\Support\Ambiente;
use App\Support\Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Crea (solo si faltan) la unidad de Finanzas, las cuentas de PRUEBA de pagos y tesorería y los firmantes de demostración del decreto de pago y del egreso. */
class PruebasPagos extends Command
{
    protected $signature = 'pruebas:pagos';

    protected $description = 'Crea (solo si faltan) la unidad DAF, las cuentas de PRUEBA de Encargado de pagos y Tesorero y los firmantes de demostración (solo SGD_AMBIENTE=pruebas).';

    public function handle(PagoConfigService $cfg): int
    {
        if (! Ambiente::esPruebas()) {
            $this->error('SGD_AMBIENTE no es «pruebas»: en producción cree la unidad, los usuarios y los firmantes desde la Administración y el módulo Decreto de pago.');

            return self::FAILURE;
        }
        foreach (['tesorero', 'contabilidad'] as $r) {
            if (! Rol::query()->where('clave', $r)->exists()) {
                $this->error("Falta el rol $r: ejecute php artisan migrate --force.");

                return self::FAILURE;
            }
        }
        $cfg->tipo('decreto_pago');
        $cfg->tipo('egreso');
        $unidad = Unidad::query()->firstOrCreate(['codigo' => 'DAF'], ['nombre' => 'Dirección de Administración y Finanzas', 'activo' => true]);
        $filas = [];
        foreach ([[10000017, 'Bernardo', 'Pagos Demo', 'contabilidad', 'Encargado de pagos', false], [10000018, 'Tamara', 'Tesorera Demo', 'tesorero', 'Tesorera Municipal', true]] as [$cuerpo, $nom, $ape, $rol, $cargo, $firma]) {
            $run = $cuerpo.'-'.Run::dv((string) $cuerpo);
            if (Usuario::query()->where('run', $run)->exists()) {
                $this->line("Ya existe el usuario $run: sin cambios.");

                continue;
            }
            Usuario::query()->create(['run' => $run, 'nombres' => $nom, 'apellidos' => $ape, 'correo' => strtolower($nom).'.'.strtolower(explode(' ', $ape)[0]).'@demo.santodomingo.invalid', 'cargo' => $cargo, 'rol' => $rol,
                'unidad_id' => $unidad->id, 'puede_firmar' => $firma, 'activo' => true]);
            $pw = $this->generar();
            CuentaPrueba::query()->updateOrCreate(['run' => $run], ['tipo' => 'funcionario', 'nombres' => $nom, 'apellidos' => $ape, 'password' => Hash::make($pw), 'activo' => true]);
            $filas[] = [$run, "$nom $ape", $rol, $pw];
        }
        if ($filas) {
            $this->table(['RUN (usuario)', 'Nombre', 'Rol', 'Contraseña (se muestra una vez)'], $filas);
        }
        $alc = Usuario::query()->where('rol', 'alcalde')->where('activo', true)->first();
        $sec = Usuario::query()->where('rol', 'secretario')->where('activo', true)->first();
        $tes = Usuario::query()->where('rol', 'tesorero')->where('activo', true)->first();
        if (! PagoFirmante::query()->where('documento', 'decreto_pago')->exists() && $alc && $sec) {
            PagoFirmante::query()->create(['documento' => 'decreto_pago', 'usuario_id' => $sec->id, 'cargo' => 'Secretaria Municipal', 'funcion' => 'visacion', 'firma_tipo' => 'avanzada', 'orden' => 1, 'activo' => true]);
            PagoFirmante::query()->create(['documento' => 'decreto_pago', 'usuario_id' => $alc->id, 'cargo' => 'Alcalde', 'funcion' => 'firma', 'firma_tipo' => 'avanzada', 'orden' => 2, 'activo' => true]);
            $this->info('Firmantes de demostración del decreto de pago creados.');
        }
        if (! PagoFirmante::query()->where('documento', 'egreso')->exists() && $tes) {
            PagoFirmante::query()->create(['documento' => 'egreso', 'usuario_id' => $tes->id, 'cargo' => 'Tesorera Municipal', 'funcion' => 'firma', 'firma_tipo' => 'ambas', 'orden' => 1, 'activo' => true]);
            $this->info('Firmante de demostración del egreso creado.');
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
