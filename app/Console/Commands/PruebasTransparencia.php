<?php

namespace App\Console\Commands;

use App\Models\CuentaPrueba;
use App\Models\Rol;
use App\Models\Tramite;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Support\Ambiente;
use App\Support\Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Crea (solo si faltan) la unidad de Transparencia, el trámite de acceso a la información y la cuenta de PRUEBA del rol Transparencia en una instalación existente. */
class PruebasTransparencia extends Command
{
    protected $signature = 'pruebas:transparencia';

    protected $description = 'Crea (solo si faltan) la unidad de Transparencia, el trámite de acceso a la información y la cuenta de PRUEBA del rol Transparencia (solo SGD_AMBIENTE=pruebas).';

    public function handle(): int
    {
        if (! Ambiente::esPruebas()) {
            $this->error('SGD_AMBIENTE no es «pruebas»: en producción cree la unidad, el trámite y los usuarios de Transparencia desde la Administración.');

            return self::FAILURE;
        }
        if (! Rol::query()->where('clave', 'transparencia')->exists()) {
            $this->error('Falta el rol transparencia: ejecute php artisan migrate --force.');

            return self::FAILURE;
        }
        $unidad = Unidad::query()->firstOrCreate(['codigo' => 'TRANSP'], ['nombre' => 'Unidad de Transparencia', 'activo' => true]);
        if (! Tramite::query()->where('es_transparencia', true)->exists()) {
            $t = Tramite::query()->create(['codigo_interno' => 'TRANSP-SAI', 'nombre' => 'Solicitud de acceso a la información pública (Ley 20.285)',
                'descripcion' => 'Solicite información que obre en poder de la municipalidad. Se responde en 20 días hábiles (prorrogables una vez).', 'plazo_dias' => 20, 'plazo_habiles' => true, 'es_transparencia' => true,
                'activo' => true, 'estado' => 'publicado', 'publicado_en' => now(), 'unidad_propietaria_id' => $unidad->id, 'version' => 0]);
            $t->unidades()->sync([$unidad->id]);
            $this->info('Trámite creado: '.$t->nombre);
        }
        $run = '10000015-'.Run::dv('10000015');
        if (Usuario::query()->where('run', $run)->exists()) {
            $this->line("Ya existe el usuario $run: sin cambios.");

            return self::SUCCESS;
        }
        Usuario::query()->create(['run' => $run, 'nombres' => 'Teresa', 'apellidos' => 'Transparencia Demo', 'correo' => 'teresa.transparencia@demo.santodomingo.invalid', 'cargo' => 'Encargada de Transparencia',
            'rol' => 'transparencia', 'unidad_id' => $unidad->id, 'puede_firmar' => false, 'activo' => true]);
        $pw = $this->generar();
        CuentaPrueba::query()->updateOrCreate(['run' => $run], ['tipo' => 'funcionario', 'nombres' => 'Teresa', 'apellidos' => 'Transparencia Demo', 'password' => Hash::make($pw), 'activo' => true]);
        $this->table(['RUN (usuario)', 'Nombre', 'Rol', 'Contraseña (se muestra una vez)'], [[$run, 'Teresa Transparencia Demo', 'transparencia', $pw]]);

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
