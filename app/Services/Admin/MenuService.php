<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\MenuItem;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Espacio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Barra de menú configurable. Los ítems del sistema (definidos aquí, con su permiso) pueden renombrarse, reordenarse y,
 * salvo los protegidos, ocultarse; el administrador puede agregar enlaces propios (ruta interna o https://) por área y rol.
 * Un ítem nunca se muestra a quien no tiene el permiso: el menú no concede acceso (la autorización sigue en el servidor).
 */
class MenuService
{
    /** @var array<string,array{etiqueta:string,ruta:string,patron:string,areas:array<int,string>,permiso:?string,grupo:?string,orden:int,protegido:bool}>|null */
    private ?array $base = null;

    private ?Collection $filas = null;

    public function __construct(private readonly AuditoriaService $audit) {}

    /** @return array<string,array{etiqueta:string,ruta:string,patron:string,areas:array<int,string>,permiso:?string,grupo:?string,orden:int,protegido:bool}> */
    public function sistema(): array
    {
        $a = 'Administración';
        $s = 'Seguridad y operación';
        $i = fn (string $e, string $r, string $p, array $areas, ?string $perm, ?string $g, int $o, bool $prot = false) => ['etiqueta' => $e, 'ruta' => $r, 'patron' => $p, 'areas' => $areas, 'permiso' => $perm, 'grupo' => $g, 'orden' => $o, 'protegido' => $prot];

        return $this->base ??= [
            'inicio' => $i('Inicio', 'inicio', '/', ['publico', 'ciudadano'], null, null, 10, true),
            'catalogo' => $i('Trámites y servicios', 'catalogo', 'tramites*', ['publico', 'ciudadano'], null, null, 15),
            'publico' => $i('Documentos públicos', 'publico.listado', 'publico*', ['publico', 'ciudadano'], null, null, 20),
            'validar' => $i('Validar documento', 'validar.form', 'validar*', ['publico', 'ciudadano'], null, null, 30),
            'mis_solicitudes' => $i('Mis solicitudes', 'ciudadano.index', 'ciudadano*', ['ciudadano'], null, null, 40, true),
            'bandeja' => $i('Inicio', 'app.bandeja', 'app', ['app'], null, null, 10, true),
            'vigentes' => $i('Documentos vigentes', 'app.vigentes', 'app/vigentes', ['app'], 'vigentes.ver', null, 20),
            'formatos' => $i('Formatos y plantillas', 'app.formatos', 'app/formatos*', ['app'], 'plantillas.ver', null, 25),
            'comunicaciones' => $i('Comunicaciones oficiales', 'app.comunicaciones', 'app/comunicaciones*', ['app'], 'comunicaciones.ver', null, 30),
            'transparencia' => $i('Transparencia', 'app.transparencia', 'app/transparencia', ['app'], 'transparencia.ver', null, 24),
            'transp_activa' => $i('Transparencia activa', 'app.transparencia.activa', 'app/transparencia/activa*', ['app'], 'transparencia.ver', null, 25),
            'transp_plazos' => $i('Plazos y feriados', 'app.transparencia.plazos', 'app/transparencia/plazos*', ['app'], 'transparencia.ver', null, 26),
            'transp_reportes' => $i('Reportes de transparencia', 'app.transparencia.reportes', 'app/transparencia/reportes*', ['app'], 'transparencia.reportes', null, 27),
            'transp_req' => $i('Requerimientos de transparencia', 'app.transparencia.requerimientos', 'app/transparencia/requerimientos*', ['app'], 'transparencia.ver,transparencia.responder', null, 28),
            'seguimiento' => $i('Seguimiento de solicitudes', 'app.seguimiento', 'app/seguimiento*', ['app'], 'seguimiento.consultar', null, 22),
            'oirs' => $i('Panel OIRS', 'app.oirs', 'app/oirs*', ['app'], 'oirs.ver', null, 23),
            'casilla' => $i('Casilla Única', 'app.casilla', 'app/casilla*', ['app'], 'notificaciones.gestionar,casilla.gestionar', null, 33),
            'archivo' => $i('Archivo municipal', 'app.archivo.index', 'app/archivo*', ['app'], 'archivo.ver', null, 45),
            'docdigital' => $i('DocDigital', 'app.docdigital', 'app/docdigital*', ['app'], 'comunicaciones.ver,docdigital.gestionar', null, 32),
            'delegaciones' => $i('Delegaciones de firma', 'app.delegaciones', 'app/delegaciones*', ['app'], 'delegaciones.ver', null, 40),
            'unidades' => $i('Unidades', 'app.admin.index:unidades', 'app/admin/unidades*', ['app'], 'mantenedores.gestionar', $a, 100),
            'usuarios' => $i('Usuarios', 'app.admin.index:usuarios', 'app/admin/usuarios*', ['app'], 'mantenedores.gestionar', $a, 110),
            'roles' => $i('Roles y permisos', 'app.admin.roles', 'app/admin/roles*', ['app'], 'roles.gestionar', $a, 115),
            'tramites' => $i('Trámites y servicios', 'app.tramites.index', 'app/tramites*', ['app'], 'tramites.gestionar', $a, 120),
            'tipos' => $i('Tipos de documento', 'app.admin.index:tipos', 'app/admin/tipos*', ['app'], 'mantenedores.gestionar', $a, 130),
            'plantillas' => $i('Plantillas', 'app.admin.plantillas', 'app/admin/plantillas*', ['app'], 'plantillas.gestionar', $a, 140),
            'folios' => $i('Folios', 'app.admin.folios', 'app/admin/folios*', ['app'], 'folios.gestionar', $a, 150),
            'firmas' => $i('Estilo y posición de firmas', 'app.admin.firmas', 'app/admin/firmas*', ['app'], 'firmas.gestionar', $a, 160),
            'instituciones' => $i('Instituciones', 'app.admin.index:instituciones', 'app/admin/instituciones*', ['app'], 'codificador.gestionar', $a, 170),
            'procesos' => $i('Procesos', 'app.admin.index:procesos', 'app/admin/procesos*', ['app'], 'codificador.gestionar', $a, 175),
            'codificador' => $i('Codificador', 'app.admin.codificador', 'app/admin/codificador*', ['app'], 'codificador.gestionar', $a, 180),
            'pisee' => $i('Servicios PISEE', 'app.admin.index:pisee', 'app/admin/pisee*', ['app'], 'codificador.gestionar', $a, 185),
            'api' => $i('APIs e integraciones', 'app.admin.api', 'app/admin/api*', ['app'], 'api.gestionar', $a, 190),
            'repositorio' => $i('Repositorio', 'app.admin.repositorio', 'app/admin/repositorio*', ['app'], 'repositorio.gestionar', $a, 195),
            'apariencia' => $i('Apariencia', 'app.admin.apariencia', 'app/admin/apariencia*', ['app'], 'apariencia.gestionar', $a, 200),
            'menu' => $i('Barra de menú', 'app.admin.menu', 'app/admin/menu*', ['app'], 'menu.gestionar', $a, 205),
            'seguridad' => $i('Seguridad', 'app.seguridad', 'app/seguridad*', ['app'], 'seguridad.ver', $s, 300),
            'logs' => $i('Registro de logs', 'app.logs', 'app/logs*', ['app'], 'logs.ver', $s, 310),
            'sesiones' => $i('Usuarios conectados', 'app.sesiones', 'app/sesiones*', ['app'], 'sesiones.ver', $s, 320),
            'conexiones' => $i('Conexiones y mapa', 'app.sistema.conexiones', 'app/sistema/conexiones*', ['app'], 'conexiones.ver', $s, 322),
            'recursos' => $i('Recursos del servidor', 'app.sistema.recursos', 'app/sistema/recursos*', ['app'], 'recursos.ver', $s, 325),
            'componentes' => $i('Componentes y librerías', 'app.sistema.componentes', 'app/sistema/componentes*', ['app'], 'componentes.ver', $s, 328),
            'diagnostico' => $i('Diagnóstico', 'app.diagnostico', 'app/diagnostico', ['app'], 'diagnostico.ver', $s, 330),
            'espacio' => $i('Cambiar de espacio', 'espacio', 'espacio', ['app', 'ciudadano'], 'dual', null, 5),
            'ayuda' => $i('Ayuda', 'ayuda', 'ayuda*', ['publico', 'ciudadano', 'app'], null, null, 900, true),
        ];
    }

    private function filas(): Collection
    {
        try {
            return $this->filas ??= MenuItem::query()->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Ícono (símbolo del sprite `partials.iconos`) y descripción breve de cada ítem del sistema, para el menú lateral y el inicio. */
    public const ICONOS = [
        'espacio' => 'switch', 'seguimiento' => 'search', 'transparencia' => 'book', 'transp_activa' => 'globe', 'transp_plazos' => 'clock', 'transp_reportes' => 'file-text', 'transp_req' => 'send', 'oirs' => 'clock', 'inicio' => 'home', 'bandeja' => 'home', 'catalogo' => 'clipboard', 'publico' => 'file-text', 'validar' => 'check-circle', 'mis_solicitudes' => 'folder',
        'vigentes' => 'file-check', 'formatos' => 'layout', 'comunicaciones' => 'mail', 'delegaciones' => 'pen', 'unidades' => 'building', 'usuarios' => 'users',
        'roles' => 'shield', 'archivo' => 'archive', 'docdigital' => 'send', 'casilla' => 'inbox', 'tramites' => 'clipboard', 'tipos' => 'tag', 'plantillas' => 'file-text', 'folios' => 'hash', 'firmas' => 'pen', 'instituciones' => 'sitemap',
        'procesos' => 'branch', 'codificador' => 'book', 'pisee' => 'link', 'api' => 'link', 'repositorio' => 'database', 'apariencia' => 'palette', 'menu' => 'menu',
        'seguridad' => 'shield', 'logs' => 'activity', 'sesiones' => 'monitor', 'conexiones' => 'globe', 'recursos' => 'cpu', 'componentes' => 'package', 'diagnostico' => 'wrench', 'ayuda' => 'help',
    ];

    public const DESCRIPCIONES = [
        'seguimiento' => 'Consulte por RUN, fechas, dirección, etapa o trámite: en qué está, quién la tiene y si terminó.', 'oirs' => 'Solicitudes, responsables y plazos vencidos o por vencer.',
        'transparencia' => 'Consultas de acceso a la información: gestión, plazos, prórrogas y derivaciones.', 'transp_activa' => 'Calendario mensual de publicaciones obligatorias.', 'transp_plazos' => 'Feriados y parámetros de los plazos de transparencia.', 'transp_reportes' => 'Reportes de transparencia pasiva y activa, exportables.', 'transp_req' => 'Requerimientos de información dirigidos a su dirección.',
        'espacio' => 'Elija si trabaja como funcionario(a) o realiza trámites como vecino(a).',
        'vigentes' => 'Decretos, oficios y demás documentos firmados y vigentes.', 'casilla' => 'Notificaciones a la ciudadanía por Casilla Única: pendientes, evidencia y reintentos.', 'docdigital' => 'Estado de la conexión, comunicaciones recibidas y enviadas, sincronización y reintentos.', 'archivo' => 'Microformas del archivo municipal: registro, certificación y custodia.', 'formatos' => 'Formatos y plantillas disponibles para su dirección.',
        'comunicaciones' => 'Comunicaciones oficiales recibidas y enviadas por DocDigital.', 'delegaciones' => 'Delegaciones de firma del alcalde.',
        'tramites' => 'Cree y publique los trámites y servicios de su dirección.', 'usuarios' => 'Altas, roles y direcciones de las personas usuarias.',
        'roles' => 'Roles personalizados y permisos.', 'plantillas' => 'Editor de plantillas con visibilidad por dirección.', 'apariencia' => 'Logo, colores, encabezado y pie.',
        'seguridad' => 'Bitácora de auditoría, incidentes y verificación de la cadena.', 'logs' => 'Registro de eventos con filtros y exportación.', 'conexiones' => 'Mapa y ranking de las conexiones al sistema.',
        'recursos' => 'CPU, memoria, disco y base de datos del servidor.', 'api' => 'Estado y configuración de las integraciones.', 'ayuda' => 'Manuales de usuario y declaración de accesibilidad.',
        'catalogo' => 'Busque un trámite y vea qué documentos necesita.', 'validar' => 'Compruebe la autenticidad de un documento con su código.', 'publico' => 'Decretos, ordenanzas y otros documentos públicos.',
        'mis_solicitudes' => 'Siga el estado de sus solicitudes.',
    ];

    public function olvidar(): void
    {
        $this->filas = null;
    }

    private function urlDe(string $ruta): ?string
    {
        [$nombre, $param] = array_pad(explode(':', $ruta, 2), 2, null);
        if (! Route::has($nombre)) {
            return null;
        }

        return $param ? route($nombre, $param) : route($nombre);
    }

    /** @return array<int,array{url:string,etiqueta:string,patron:string,grupo:?string,nueva:bool}> */
    public function items(string $area, ?Usuario $u): array
    {
        $filas = $this->filas();
        $por = $filas->whereNotNull('clave')->keyBy('clave');
        $out = [];
        foreach ($this->sistema() as $clave => $d) {
            if (! in_array($area, $d['areas'], true) || ($d['permiso'] && ! ($u && collect(explode(',', $d['permiso']))->contains(fn ($p) => $p === 'dual' ? Espacio::dual() : $u->tienePermiso($p))))) {
                continue;
            }
            $o = $por->get($clave);
            if ($o && ! $o->activo && ! $d['protegido']) {
                continue;
            }
            if ($url = $this->urlDe($d['ruta'])) {
                $out[] = ['url' => $url, 'etiqueta' => $o?->etiqueta ?: $d['etiqueta'], 'patron' => $d['patron'], 'grupo' => $d['grupo'], 'clave' => $clave, 'icono' => self::ICONOS[$clave] ?? 'file-text', 'descripcion' => self::DESCRIPCIONES[$clave] ?? '', 'nueva' => false, 'orden' => $o->orden ?? $d['orden']];
            }
        }
        foreach ($filas->whereNull('clave') as $c) {
            if (! $c->activo || $c->area !== $area) {
                continue;
            }
            $roles = array_filter(explode(',', (string) $c->roles));
            if ($roles && ! ($u && in_array($u->rolBase(), $roles, true))) {
                continue;
            }
            $out[] = ['url' => str_starts_with((string) $c->url, '/') ? url($c->url) : (string) $c->url, 'etiqueta' => $c->etiqueta, 'patron' => ltrim((string) parse_url((string) $c->url, PHP_URL_PATH), '/') ?: '/', 'clave' => null, 'icono' => 'external', 'descripcion' => '', 'grupo' => null, 'nueva' => (bool) $c->nueva_pestana, 'orden' => $c->orden];
        }
        usort($out, fn ($a, $b) => [$a['orden'], $a['etiqueta']] <=> [$b['orden'], $b['etiqueta']]);

        return $out;
    }

    // ------------------------------------------------------------------ administración
    /** @param array<string,mixed> $d */
    public function guardarSistema(string $clave, array $d, Usuario $u): void
    {
        $def = $this->sistema()[$clave] ?? throw new FlujoException('Ítem de menú desconocido.');
        $etq = $this->etiqueta((string) ($d['etiqueta'] ?? ''));
        $orden = $this->orden($d['orden'] ?? 100);
        $activo = $def['protegido'] ? true : (bool) ($d['activo'] ?? false);
        MenuItem::query()->updateOrCreate(['clave' => $clave], ['area' => $def['areas'][0], 'etiqueta' => $etq, 'orden' => $orden, 'activo' => $activo]);
        $this->olvidar();
        $this->audit->registrar('menu.guardar', 'menu_item', $clave);
    }

    public function restablecerSistema(string $clave): void
    {
        MenuItem::query()->where('clave', $clave)->delete();
        $this->olvidar();
        $this->audit->registrar('menu.restablecer', 'menu_item', $clave);
    }

    /** @param array<string,mixed> $d */
    public function guardarPropio(array $d, ?MenuItem $m, Usuario $u): MenuItem
    {
        if (! in_array($d['area'] ?? '', ['publico', 'ciudadano', 'app'], true)) {
            throw new FlujoException('Área inválida.');
        }
        $roles = array_values(array_unique(array_filter((array) ($d['roles'] ?? []))));
        if (array_diff($roles, Usuario::ROLES)) {
            throw new FlujoException('Rol inválido.');
        }
        if ($roles && $d['area'] !== 'app') {
            throw new FlujoException('Solo los enlaces del área de funcionarios pueden limitarse por rol.');
        }
        $url = AparienciaService::urlSegura((string) ($d['url'] ?? ''));
        $m ??= new MenuItem;
        $m->fill(['area' => $d['area'], 'clave' => null, 'etiqueta' => $this->etiqueta((string) ($d['etiqueta'] ?? '')), 'url' => $url, 'nueva_pestana' => (bool) ($d['nueva_pestana'] ?? false),
            'orden' => $this->orden($d['orden'] ?? 100), 'activo' => (bool) ($d['activo'] ?? true), 'roles' => $roles ? implode(',', $roles) : null])->save();
        $this->olvidar();
        $this->audit->registrar('menu.propio_guardar', 'menu_item', $m->id);

        return $m;
    }

    public function eliminarPropio(MenuItem $m): void
    {
        if ($m->clave !== null) {
            throw new FlujoException('Los ítems del sistema no se eliminan: se pueden ocultar o restablecer.');
        }
        $id = $m->id;
        $m->delete();
        $this->olvidar();
        $this->audit->registrar('menu.propio_eliminar', 'menu_item', $id);
    }

    private function etiqueta(string $e): string
    {
        $e = trim($e);
        if ($e === '' || mb_strlen($e) > 40 || preg_match('/[\r\n\0<>]/', $e)) {
            throw new FlujoException('La etiqueta debe tener entre 1 y 40 caracteres, sin símbolos < >.');
        }

        return $e;
    }

    private function orden(mixed $o): int
    {
        if (! is_numeric($o) || (int) $o < 0 || (int) $o > 999) {
            throw new FlujoException('El orden debe estar entre 0 y 999.');
        }

        return (int) $o;
    }
}
