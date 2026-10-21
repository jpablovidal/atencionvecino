<?php

namespace App\Services\Directorio;

use App\Models\Rol;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Run;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    /** @var array<string,list<string>> */
    private array $columnasCache = [];

    /**
     * Columnas de la lista cerrada que existen en la tabla (rut y dv son indispensables; el resto es opcional). Así una columna
     * ausente (p. ej. «unidad») no impide reconocer a un funcionario.
     *
     * @return list<string>
     */
    private function columnas(): array
    {
        $k = md5(json_encode(config('sgd.directorio.host').config('sgd.directorio.database').$this->tabla()));
        if (! isset($this->columnasCache[$k])) {
            $existen = array_map('strtolower', Schema::connection(self::CONEXION)->getColumnListing($this->tabla()));
            $this->columnasCache[$k] = array_values(array_intersect(self::COLUMNAS, $existen));
        }

        return $this->columnasCache[$k];
    }

    /** Lee una propiedad opcional de la fila. */
    private static function campo(object $f, string $c): string
    {
        return isset($f->{$c}) ? (string) $f->{$c} : '';
    }

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
            $this->conexion();
            $cols = $this->columnas();
            if (! in_array('rut', $cols, true) || ! in_array('dv', $cols, true)) {
                throw new \RuntimeException('La tabla no tiene las columnas rut y dv.');
            }
            $filas = $this->conexion()->table($this->tabla())->select($cols)->where('rut', (int) $num)->limit(10)->get();
        } catch (\Throwable $e) {
            Log::warning('Directorio de funcionarios: consulta fallida ('.class_basename($e).').');
            throw new DirectorioException('No fue posible consultar el directorio de funcionarios.');
        }
        foreach ($filas as $f) {
            if (strtoupper(trim(self::campo($f, 'dv'))) !== strtoupper($dv) || ! $this->vigente($f)) {
                continue;
            }
            $t = fn ($v, int $n) => mb_substr(trim((string) $v), 0, $n);

            return ['run' => $run, 'nombres' => $t(self::campo($f, 'nombre'), 120), 'apellidos' => $t(self::campo($f, 'apellido'), 120), 'cargo' => $t(self::campo($f, 'cargo'), 120), 'direccion' => $t(self::campo($f, 'direccion'), 120),
                'departamento' => $t(self::campo($f, 'departamento'), 120), 'unidad' => $t(self::campo($f, 'unidad'), 120), 'correo' => $t(self::campo($f, 'mail'), 190)];
        }

        return null;
    }

    /** Habilitado «si», credencial «Vigente» y sin fecha de término vencida. */
    private function vigente(object $f): bool
    {
        return $this->motivoNoVigente($f) === null;
    }

    /** null si está vigente; si no, el motivo (sin datos personales). */
    private function motivoNoVigente(object $f): ?string
    {
        if (! in_array(mb_strtolower(trim(self::campo($f, 'habilitado'))), ['si', 'sí', '1', 's'], true)) {
            return 'la columna «habilitado» no vale «si»';
        }
        if (mb_strtolower(trim(self::campo($f, 'credencial'))) !== 'vigente') {
            return 'la columna «credencial» no vale «Vigente»';
        }
        $t = self::campo($f, 'fecha_termino');
        if ($t !== '' && $t !== '0000-00-00' && strtotime($t) < strtotime('today')) {
            return 'la «fecha_termino» ya venció';
        }

        return null;
    }

    /**
     * Diagnóstico de por qué un RUN es o no reconocido como funcionario(a). No muestra datos personales.
     *
     * @return list<array{0:string,1:bool,2:string}>
     */
    public function diagnosticarRun(string $run): array
    {
        $out = [['Directorio activado y configurado', self::habilitado(), self::habilitado() ? 'sí' : 'No: active el directorio y complete servidor, base de datos, usuario y contraseña (Administración → APIs → Directorio).']];
        if (! self::habilitado()) {
            return $out;
        }
        [$num, $dv] = array_pad(explode('-', $run, 2), 2, '');
        try {
            $this->conexion()->getPdo();
            $cols = $this->columnas();
        } catch (\Throwable) {
            return array_merge($out, [['Conexión con la base', false, 'No fue posible conectar o leer la tabla: revise servidor, base, usuario, contraseña y que el usuario tenga permiso SELECT sobre la tabla.']]);
        }
        $out[] = ['Conexión con la base y tabla «'.$this->tabla().'»', true, 'columnas leídas: '.implode(', ', $cols)];
        if (! in_array('rut', $cols, true) || ! in_array('dv', $cols, true)) {
            return array_merge($out, [['Columnas rut y dv', false, 'La tabla no las tiene.']]);
        }
        try {
            $filas = $this->conexion()->table($this->tabla())->select($cols)->where('rut', (int) $num)->limit(10)->get();
        } catch (\Throwable) {
            return array_merge($out, [['Consulta por RUT', false, 'La consulta falló (revise permisos).']]);
        }
        $out[] = ['El RUT figura en la tabla', $filas->isNotEmpty(), $filas->isNotEmpty() ? $filas->count().' fila(s) con ese RUT' : 'No hay ninguna fila con ese RUT: la persona entrará solo como vecino(a).'];
        if ($filas->isEmpty()) {
            return $out;
        }
        $mismo = $filas->filter(fn ($f) => strtoupper(trim(self::campo($f, 'dv'))) === strtoupper($dv));
        $out[] = ['El dígito verificador coincide', $mismo->isNotEmpty(), $mismo->isNotEmpty() ? 'sí' : 'No coincide con el «dv» de la tabla.'];
        if ($mismo->isEmpty()) {
            return $out;
        }
        $motivos = $mismo->map(fn ($f) => $this->motivoNoVigente($f));
        $ok = $motivos->contains(null);
        $out[] = ['Vigente (habilitado «si», credencial «Vigente», sin término vencido)', $ok, $ok ? 'sí: se reconoce como funcionario(a)' : 'No: '.$motivos->first()];

        return $out;
    }

    public static function loginUsuarioHabilitado(): bool
    {
        return self::habilitado() && (bool) config('sgd.directorio.login_usuario', true);
    }

    /**
     * Ingreso con «username» y contraseña del directorio. La contraseña se verifica SOLO contra el hash bcrypt de la columna `pass`
     * (nunca contra la columna `password`, que puede estar en texto plano); sin un hash válido no hay ingreso. Devuelve el RUN o null.
     *
     * @throws DirectorioException si el directorio no responde
     */
    public function autenticar(string $username, string $clave): ?string
    {
        $falso = '$2y$12$wugNHhvPcVl3Q/Ov6fLV.ekUSbZV.fXIZ1Wekxz6T1aMXX58cYswW'; // costo constante aunque el usuario no exista
        try {
            $this->conexion();
            $filas = $this->conexion()->table($this->tabla())->select(array_merge($this->columnas(), ['username', 'pass']))->whereRaw('lower(username) = ?', [mb_strtolower($username)])->limit(5)->get();
        } catch (\Throwable) {
            throw new DirectorioException('No fue posible consultar el directorio de funcionarios.');
        }
        $f = $filas->first(fn ($x) => strtolower(trim((string) $x->username)) === strtolower($username) && $this->vigente($x));
        $hash = $f ? (string) $f->pass : '';
        $valido = (bool) preg_match('/^\$2[aby]\$\d{2}\$.{53}$/', $hash);
        $ok = Hash::check($clave, $valido ? $hash : $falso);
        if (! $f || ! $valido || ! $ok) {
            return null;
        }

        return Run::normalizar(trim((string) $f->rut).trim((string) $f->dv));
    }

    /**
     * Usuario funcionario(a) local para este RUN según el directorio: lo crea la primera vez, mantiene sus datos y da de baja a quien dejó de figurar.
     * Los usuarios creados a mano (p. ej. administración) no dependen del directorio.
     */
    public function resolver(string $run): ?Usuario
    {
        $existente = Usuario::query()->where('run', $run)->first();
        $u = $existente && $existente->activo ? $existente : null; // una cuenta desactivada no se reactiva ni se vuelve a crear
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
        if (! $existente && $f !== null) {
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
            $indispensables = array_values(array_diff(['rut', 'dv', 'habilitado', 'credencial'], $cols));
            $out[] = ['Tabla «'.$this->tabla().'» con las columnas necesarias', $indispensables === [], $indispensables === [] ? ($faltan === [] ? 'todas las columnas' : 'indispensables presentes; opcionales ausentes: '.implode(', ', $faltan)) : 'faltan indispensables: '.implode(', ', $indispensables)];
            if ($indispensables === []) {
                $n = $con->table($this->tabla())->whereRaw('lower(habilitado) in (?, ?)', ['si', 'sí'])->whereRaw('lower(credencial) = ?', ['vigente'])->count();
                $out[] = ['Funcionarios habilitados y vigentes', $n > 0, $n.' en el directorio'];
            }
            $sens = array_values(array_intersect(['password', 'pass'], $cols));
            if ($sens) {
                $out[] = ['Contraseñas en la tabla', false, 'La tabla guarda las columnas «'.implode('», «', $sens).'». El sistema NO lee «password» (solo el hash «pass» en el ingreso por usuario), pero conviene eliminar «password» de esta base (suele contener contraseñas en texto plano) y usar un usuario de solo lectura.'];
            }
        } catch (\Throwable) {
            $out[] = ['Conexión con la base', false, 'No fue posible conectar: revise servidor, puerto, base, usuario y contraseña (y que el usuario tenga permiso de lectura).'];
        }

        return $out;
    }
}
