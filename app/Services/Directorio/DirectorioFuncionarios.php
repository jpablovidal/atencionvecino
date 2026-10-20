<?php

namespace App\Services\Directorio;

use App\Models\Rol;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Directorio institucional de funcionarios (base «sistemas_usuarios», tabla «sistemas_users»): quien se autentica con ClaveÚnica y su RUN figura
 * habilitado en el directorio es funcionario(a); si no, es vecino(a). Solo se leen datos de identificación y cargo: NUNCA las columnas de
 * contraseña (`password`, `pass`). La conexión es de solo lectura y se configura desde el panel (Administración → APIs → Directorio).
 */
class DirectorioFuncionarios
{
    public const CONEXION = 'sgd_directorio';

    /** Columnas que se leen (lista cerrada). */
    private const COLUMNAS = ['rut', 'dv', 'nombre', 'apellido', 'cargo', 'direccion', 'departamento', 'unidad', 'mail', 'habilitado', 'credencial', 'fecha_termino'];

    public function __construct(private readonly AuditoriaService $audit) {}

    public static function habilitado(): bool
    {
        $c = (array) config('sgd.directorio');

        return ! empty($c['enabled']) && ! empty($c['host']) && ! empty($c['database']) && ! empty($c['username']);
    }

    public function tabla(): string
    {
        $t = (string) config('sgd.directorio.tabla', 'sistemas_users');

        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $t) ? $t : 'sistemas_users';
    }

    public function conexion(): ConnectionInterface
    {
        $def = config('database.connections.'.self::CONEXION);
        if (! is_array($def) || ($def['_origen'] ?? '') === 'panel') {
            $c = (array) config('sgd.directorio');
            $def = [
                'driver' => 'mysql', 'host' => (string) $c['host'], 'port' => (int) ($c['port'] ?: 3306), 'database' => (string) $c['database'], 'username' => (string) $c['username'],
                'password' => (string) ($c['password'] ?? ''), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => false,
                'options' => [\PDO::ATTR_TIMEOUT => 5], '_origen' => 'panel',
            ];
            static $firma = null;
            if ($firma !== md5(json_encode($def))) {
                DB::purge(self::CONEXION);
                $firma = md5(json_encode($def));
            }
            config(['database.connections.'.self::CONEXION => $def]);
        }

        return DB::connection(self::CONEXION);
    }

    /**
     * Busca al funcionario por RUN (formato 12345678-9). Devuelve null si no figura habilitado y vigente.
     *
     * @return array{run:string,nombres:string,apellidos:string,cargo:string,direccion:string,departamento:string,unidad:string,correo:string}|null
     *
     * @throws DirectorioException si el directorio no responde
     */
    public function buscar(string $run): ?array
    {
        [$num, $dv] = array_pad(explode('-', $run, 2), 2, '');
        try {
            $filas = $this->conexion()->table($this->tabla())->select(self::COLUMNAS)->where('rut', (int) $num)->limit(10)->get();
        } catch (\Throwable) {
            throw new DirectorioException('No fue posible consultar el directorio de funcionarios.');
        }
        foreach ($filas as $f) {
            if (strtoupper(trim((string) $f->dv)) !== strtoupper($dv)) {
                continue;
            }
            if (! in_array(mb_strtolower(trim((string) $f->habilitado)), ['si', 'sí', '1', 's'], true) || mb_strtolower(trim((string) $f->credencial)) !== 'vigente') {
                continue;
            }
            if (! empty($f->fecha_termino) && $f->fecha_termino !== '0000-00-00' && strtotime((string) $f->fecha_termino) < strtotime('today')) {
                continue;
            }
            $t = fn ($v, int $n) => mb_substr(trim((string) $v), 0, $n);

            return ['run' => $run, 'nombres' => $t($f->nombre, 120), 'apellidos' => $t($f->apellido, 120), 'cargo' => $t($f->cargo, 120), 'direccion' => $t($f->direccion, 120),
                'departamento' => $t($f->departamento, 120), 'unidad' => $t($f->unidad, 120), 'correo' => $t($f->mail, 190)];
        }

        return null;
    }

    /**
     * Usuario funcionario(a) local para este RUN según el directorio: lo crea la primera vez, mantiene sus datos y da de baja a quien dejó de figurar.
     * Los usuarios creados a mano (p. ej. administración) no dependen del directorio.
     */
    public function resolver(string $run): ?Usuario
    {
        $u = Usuario::query()->where('run', $run)->where('activo', true)->first();
        if (! self::habilitado()) {
            return $u;
        }
        try {
            $f = $this->buscar($run);
        } catch (DirectorioException) {
            return $u; // directorio caído: se respeta lo ya existente; no se crea nadie nuevo
        }
        if ($u && $u->directorio && $f === null) {
            $u->forceFill(['activo' => false])->save();
            DB::table('sessions')->where('user_id', $u->id)->delete();
            $this->audit->registrar('usuario.directorio_baja', 'usuario', $u->id, [], true);

            return null;
        }
        if (! $u && $f !== null) {
            return $this->crear($f);
        }
        if ($u && $u->directorio && $f !== null) {
            $u->fill(array_filter(['nombres' => $f['nombres'], 'apellidos' => $f['apellidos'], 'cargo' => $f['cargo'], 'correo' => $f['correo']], fn ($v) => $v !== ''))->save();
        }

        return $u;
    }

    /** @param array<string,string> $f */
    private function crear(array $f): Usuario
    {
        $rol = (string) config('sgd.directorio.rol_defecto', 'funcionario');
        if (Rol::query()->where('clave', $rol)->where('activo', true)->value('rol_base') !== 'funcionario') {
            $rol = 'funcionario'; // solo roles de base «funcionario»: nadie se vuelve administrador por figurar en el directorio
        }
        $u = new Usuario(['run' => $f['run'], 'nombres' => $f['nombres'] ?: 'Funcionario(a)', 'apellidos' => $f['apellidos'] ?: '-', 'cargo' => $f['cargo'] ?: null,
            'correo' => $f['correo'] ?: strtolower(str_replace('-', '', $f['run'])).'@sin-correo.invalid', 'rol' => $rol, 'unidad_id' => $this->unidad($f)?->id, 'puede_firmar' => false, 'activo' => true]);
        $u->forceFill(['directorio' => true])->save();
        $this->audit->registrar('usuario.directorio_alta', 'usuario', $u->id, ['rol' => $rol, 'unidad_id' => $u->unidad_id], true);

        return $u;
    }

    /** Unidad local equivalente a la dirección del directorio (por nombre); si no existe y está permitido, se crea. */
    private function unidad(array $f): ?Unidad
    {
        $nombre = $f['direccion'] ?: $f['departamento'];
        if ($nombre === '') {
            return null;
        }
        $u = Unidad::query()->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->first();
        if ($u || ! config('sgd.directorio.crear_unidades', true)) {
            return $u;
        }
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre)) ?: 'DIR', 0, 20));
        $codigo = $base;
        for ($i = 2; Unidad::query()->where('codigo', $codigo)->exists(); $i++) {
            $codigo = substr($base, 0, 26).$i;
        }

        return Unidad::query()->create(['codigo' => $codigo, 'nombre' => $nombre, 'activo' => true]);
    }

    /**
     * Verificación de la conexión y de la tabla (sin mostrar datos personales).
     *
     * @return list<array{0:string,1:bool,2:string}>
     */
    public function probar(): array
    {
        if (! self::habilitado()) {
            return [['Directorio', false, 'Falta activarlo o completar servidor, base de datos y usuario.']];
        }
        $out = [];
        try {
            $con = $this->conexion();
            $con->getPdo();
            $out[] = ['Conexión con la base', true, 'conectado a '.config('sgd.directorio.host').'/'.config('sgd.directorio.database')];
            $cols = array_map('strtolower', Schema::connection(self::CONEXION)->getColumnListing($this->tabla()));
            $faltan = array_values(array_diff(self::COLUMNAS, $cols));
            $out[] = ['Tabla «'.$this->tabla().'» con las columnas necesarias', $faltan === [], $faltan === [] ? 'rut, dv, nombre, apellido, cargo, direccion, departamento, unidad, mail, habilitado, credencial, fecha_termino' : 'faltan: '.implode(', ', $faltan)];
            if ($faltan === []) {
                $n = $con->table($this->tabla())->whereRaw('lower(habilitado) in (?, ?)', ['si', 'sí'])->whereRaw('lower(credencial) = ?', ['vigente'])->count();
                $out[] = ['Funcionarios habilitados y vigentes', $n > 0, $n.' en el directorio'];
            }
            $sens = array_values(array_intersect(['password', 'pass'], $cols));
            if ($sens) {
                $out[] = ['Contraseñas en la tabla', false, 'La tabla guarda las columnas «'.implode('», «', $sens).'». El sistema NO las lee, pero conviene eliminarlas de esta base (la columna «password» suele contener contraseñas en texto plano) y usar un usuario de solo lectura.'];
            }
        } catch (\Throwable) {
            $out[] = ['Conexión con la base', false, 'No fue posible conectar: revise servidor, puerto, base, usuario y contraseña (y que el usuario tenga permiso de lectura).'];
        }

        return $out;
    }
}
