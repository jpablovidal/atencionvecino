<?php

namespace App\Console\Commands;

use App\Models\CuentaPrueba;
use App\Models\Usuario;
use App\Support\Ambiente;
use App\Support\Run;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class PruebasCredenciales extends Command
{
    protected $signature = 'pruebas:credenciales
        {--archivo= : JSON {"RUN":"contraseña",...} con las cuentas a crear/actualizar (se borra al terminar con --borrar)}
        {--borrar : Borra el archivo de credenciales después de leerlo}
        {--rotar : Genera contraseñas nuevas aleatorias para todas las cuentas y las muestra una sola vez}
        {--desactivar : Desactiva todas las cuentas de prueba}';

    protected $description = 'Gestiona las cuentas de acceso de PRUEBAS (usuario/contraseña). Requiere SGD_AMBIENTE=pruebas.';

    public function handle(): int
    {
        if (! Ambiente::esPruebas()) {
            $this->error('SGD_AMBIENTE no es «pruebas»: este comando está deshabilitado en producción.');

            return self::FAILURE;
        }
        if ($this->option('desactivar')) {
            $n = CuentaPrueba::query()->update(['activo' => false]);
            $this->info("Cuentas desactivadas: $n");

            return self::SUCCESS;
        }
        if ($this->option('rotar')) {
            $filas = [];
            foreach (CuentaPrueba::query()->orderBy('tipo')->orderBy('run')->get() as $c) {
                $pw = $this->generar();
                $c->forceFill(['password' => Hash::make($pw), 'activo' => true])->save();
                $filas[] = [$c->run, $c->tipo, $c->nombres.' '.$c->apellidos, $pw];
            }
            $this->table(['RUN', 'Tipo', 'Nombre', 'Contraseña (se muestra una vez)'], $filas);

            return self::SUCCESS;
        }
        $f = (string) $this->option('archivo');
        if ($f === '' || ! is_file($f)) {
            $this->error('Indique --archivo=ruta.json (o use --rotar / --desactivar).');

            return self::FAILURE;
        }
        $map = json_decode((string) file_get_contents($f), true);
        if ($this->option('borrar')) {
            @unlink($f);
        }
        if (! is_array($map)) {
            $this->error('JSON inválido.');

            return self::FAILURE;
        }
        $n = 0;
        foreach ($map as $run => $pw) {
            $r = Run::normalizar((string) $run);
            if (! $r || ! is_string($pw) || strlen($pw) < 12) {
                $this->warn("Omitido «{$run}»: RUN inválido o contraseña de menos de 12 caracteres.");

                continue;
            }
            $u = Usuario::query()->where('run', $r)->first();
            [$tipo, $nom, $ape] = $u ? ['funcionario', $u->nombres, $u->apellidos] : ['ciudadano', 'Vecina', 'De Prueba '.substr($r, 0, 2)];
            CuentaPrueba::query()->updateOrCreate(['run' => $r], ['tipo' => $tipo, 'nombres' => $nom, 'apellidos' => $ape, 'password' => Hash::make($pw), 'activo' => true]);
            $n++;
        }
        $this->info("Cuentas de prueba creadas/actualizadas: $n. Vencen el ".(Ambiente::pruebasHasta()?->format('d-m-Y') ?? 'sin fecha (acceso inactivo)').'.');

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
