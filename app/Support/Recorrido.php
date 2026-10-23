<?php

namespace App\Support;

use App\Models\Expediente;
use App\Models\Tramite;
use App\Models\Usuario;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Recorrido guiado por perfil. No abre una ventana: se muestra SOBRE la pantalla real de la persona, resaltando cada elemento (`sel`, un selector CSS)
 * con una tarjeta de instrucciones, y navega a la pantalla que corresponde a cada paso (`ruta`). Un paso sin `ruta` ocurre en la misma pantalla del
 * anterior; si su elemento no está en la pantalla (por ejemplo, no hay solicitudes todavía), la tarjeta se muestra sin resaltar nada.
 * El texto describe solo lo que el sistema hace. `manual` es el encabezado del manual de usuario donde se explica el paso.
 */
class Recorrido
{
    /**
     * @return list<array{titulo:string,texto:string,ruta?:string|array{0:string,1:mixed}|null,sel?:string,manual?:string}> ruta = nombre de ruta, «ruta:parámetro» o [ruta, parámetros]
     */
    public static function pasos(string $perfil, ?Usuario $u = null): array
    {
        $u ??= auth()->user();
        $tramite = Route::has('catalogo.ficha') ? Tramite::query()->publicados()->orderBy('nombre')->first() : null;
        $ficha = $tramite ? ['catalogo.ficha', [$tramite->id, Str::slug($tramite->nombre)]] : null;

        return match ($perfil) {
            'ciudadano' => array_values(array_filter([
                ['titulo' => 'Bienvenido(a) a su espacio de trámites', 'texto' => 'Desde aquí presenta solicitudes a la Municipalidad, sigue su avance y descarga las respuestas, sin ir a una oficina. Este recorrido le muestra cada parte de la pantalla real.', 'ruta' => 'ciudadano.index', 'sel' => '.portal-hero', 'manual' => 'Su página de inicio'],
                ['titulo' => 'Sus solicitudes y avisos', 'texto' => 'Aquí aparece lo que ha presentado, con su estado y la fecha de cada movimiento. Si una solicitud necesita algo de usted, se destaca arriba.', 'sel' => '.dash-alertas, .table-responsive, .vacio', 'manual' => '4. Seguimiento del estado'],
                ['titulo' => 'Trámites más buscados', 'texto' => 'Atajos a los trámites que más piden los vecinos. También puede buscar cualquier trámite por nombre o palabra clave.', 'sel' => '.portal-masbuscado', 'manual' => 'Cómo encontrar su trámite'],
                ['titulo' => 'Encuentre su trámite', 'texto' => 'El buscador sugiere trámites mientras escribe. Cada resultado muestra qué es y quién lo atiende.', 'ruta' => 'catalogo', 'sel' => 'form[role="search"]', 'manual' => 'Cómo encontrar su trámite'],
                ['titulo' => 'La lista de trámites', 'texto' => 'Elija uno para ver su ficha: qué es, cuánto demora, quién lo atiende y qué documentos debe adjuntar.', 'sel' => '.portal-resultados, .vacio', 'manual' => 'Cómo encontrar su trámite'],
                $ficha ? ['titulo' => 'Revise qué necesita antes de empezar', 'texto' => 'La ficha del trámite lista los documentos que debe adjuntar, el plazo de respuesta y la dirección municipal responsable.', 'ruta' => $ficha, 'sel' => '.portal-docs', 'manual' => '2. Presentar una solicitud'] : null,
                $ficha ? ['titulo' => 'Inicie el trámite', 'texto' => 'Con «Realizar trámite» entra al formulario. Se usa su ClaveÚnica para acreditar quién es; sus nombres y RUN vienen de ClaveÚnica y no se pueden modificar.', 'sel' => 'a.btn.btn-primary[href*="iniciar"]', 'manual' => '1. Cómo ingresar'] : null,
                $tramite ? ['titulo' => 'Complete su solicitud en pasos', 'texto' => 'Sus datos y el detalle (con contador de caracteres) · los documentos (puede verlos antes de enviar, reemplazarlos o tomar una fotografía desde el teléfono) · cómo desea ser notificado(a) · revisar y enviar, indicando si autoriza el tratamiento de sus datos. Aquí también puede presentar la solicitud a nombre de otra persona.', 'ruta' => ['ciudadano.nueva', $tramite->id], 'sel' => 'form[data-pasos]', 'manual' => '2. Presentar una solicitud'] : null,
                ['titulo' => 'Su menú', 'texto' => 'Desde su nombre accede al recorrido guiado, al manual del ciudadano y a cerrar sesión.', 'ruta' => 'ciudadano.index', 'sel' => '.sgd-user', 'manual' => 'Su página de inicio'],
                ['titulo' => 'Accesibilidad', 'texto' => 'Esta barra permite aumentar el texto, subir el contraste y ajustar la lectura. Funciona en todas las pantallas.', 'sel' => '.a11y-quick', 'manual' => '8. Accesibilidad'],
            ])),
            'funcionario' => array_values(array_filter([
                ['titulo' => 'Su bandeja de trabajo', 'texto' => 'Al ingresar ve su tablero con lo pendiente: visaciones y firmas, solicitudes por asignar y comunicaciones por acusar.', 'ruta' => 'app.bandeja', 'sel' => '.dash-kpis, #contenido h1', 'manual' => '1. Ingreso y bandeja'],
                ['titulo' => 'Lo que requiere su atención', 'texto' => 'Cada tarjeta lleva directo a la solicitud, el documento o el expediente. Al tomar una solicitud se crea el expediente con su código único y su índice electrónico.', 'sel' => '.dash-alertas, h2.seccion', 'manual' => '2. Tomar una solicitud y crear el expediente'],
                ['titulo' => 'Su menú de trabajo', 'texto' => 'El menú lateral muestra solo lo que su rol permite. Los grupos se abren y se cierran; en el teléfono se muestra con el botón ☰.', 'sel' => '#navegacion', 'manual' => '1. Ingreso y bandeja'],
                $u && $u->tienePermiso('vigentes.ver') ? ['titulo' => 'Documentos vigentes', 'texto' => 'Decretos, oficios y demás documentos firmados y vigentes, con filtros por año, tipo, dirección y texto.', 'ruta' => 'app.vigentes', 'sel' => '#contenido h1', 'manual' => '4. Documentos del expediente'] : null,
                self::expediente($u) ? ['titulo' => 'El expediente', 'texto' => 'Aquí se cargan archivos, se comenta, se deriva o devuelve la solicitud (con plazo para el vecino), se redactan documentos con plantillas y se envían a visación y firma. Todo queda registrado.', 'ruta' => ['app.expediente', self::expediente($u)], 'sel' => '#contenido h1', 'manual' => '3. Cargar archivos, comentar, derivar y devolver'] : null,
                self::expediente($u) ? ['titulo' => 'Partes, cierre y notificación', 'texto' => 'Al terminar, cierre el expediente y notifique la respuesta a cada parte por su canal: Casilla Única, correo, entrega física, aviso interno o DocDigital.', 'sel' => '#partes', 'manual' => '5. Cerrar, reabrir y notificar'] : null,
                ['titulo' => 'Su menú y el manual', 'texto' => 'Desde su nombre repite este recorrido, abre los manuales de su perfil y cierra sesión.', 'ruta' => 'app.bandeja', 'sel' => '.sgd-user', 'manual' => '10. Accesibilidad y atajos'],
                ['titulo' => 'Accesibilidad y atajos', 'texto' => 'La barra superior ajusta el texto y el contraste; hay atajos de teclado en todas las pantallas.', 'sel' => '.a11y-quick', 'manual' => '10. Accesibilidad y atajos'],
            ])),
            'administrador' => array_values(array_filter([
                ['titulo' => 'Menú del administrador', 'texto' => 'Su menú agrupa la administración, la seguridad y las integraciones. Un(a) superadministrador(a) puede abrir todas las vistas del sistema.', 'ruta' => 'app.bandeja', 'sel' => '#navegacion', 'manual' => '1. Menú del administrador'],
                ['titulo' => 'Direcciones, departamentos y unidades', 'texto' => 'Las direcciones se crean aquí o se importan desde el directorio de funcionarios (Integraciones › Directorio › Importar), junto con sus departamentos y unidades.', 'ruta' => 'app.admin.index:unidades', 'sel' => '#contenido h1, #contenido table', 'manual' => '2. Unidades'],
                ['titulo' => 'Usuarios y roles', 'texto' => 'Cree usuarios, asígneles rol, dirección, departamento y unidad, y defina permisos por rol y por persona. Los funcionarios del directorio entran con ClaveÚnica.', 'ruta' => 'app.admin.index:usuarios', 'sel' => '#contenido table, #contenido h1', 'manual' => '3. Usuarios'],
                ['titulo' => 'Trámites y servicios', 'texto' => 'Defina los trámites que ve la ciudadanía: sus documentos, los campos del formulario (con cálculos y listados de opciones) y la dirección responsable.', 'ruta' => 'app.tramites.index', 'sel' => '#contenido table, #contenido h1', 'manual' => '4. Trámites y servicios'],
                ['titulo' => 'Integraciones', 'texto' => 'ClaveÚnica, FirmaGob, DocDigital, correo, IA, directorio y más se configuran y validan desde pantalla; las claves se guardan cifradas.', 'ruta' => 'app.admin.api', 'sel' => '#contenido table, #contenido h1', 'manual' => '8. DocDigital'],
                ['titulo' => 'Datos personales (DPO)', 'texto' => 'Este panel muestra las solicitudes cuyo titular no autorizó el tratamiento de sus datos, y los accesos a contenido sensible.', 'ruta' => 'app.datospersonales', 'sel' => '#contenido h1', 'manual' => '10. Seguridad y auditoría'],
                ['titulo' => 'Su menú', 'texto' => 'Desde su nombre repite el recorrido y abre los manuales de su perfil.', 'sel' => '.sgd-user', 'manual' => '1. Menú del administrador'],
            ])),
            'publico' => [
                ['titulo' => 'Bienvenido(a)', 'texto' => 'Esta es la portada de trámites en línea de la Municipalidad. Sin iniciar sesión puede buscar trámites y servicios, validar documentos y consultar documentos públicos.', 'ruta' => 'inicio', 'sel' => '.portal-hero', 'manual' => 'Su página de inicio'],
                ['titulo' => 'Busque su trámite', 'texto' => 'Escriba el nombre o una palabra clave: el buscador sugiere trámites mientras escribe.', 'sel' => 'form[role="search"]', 'manual' => 'Cómo encontrar su trámite'],
                ['titulo' => 'Trámites más buscados', 'texto' => 'Atajos a los trámites que más piden los vecinos.', 'sel' => '.portal-masbuscado', 'manual' => 'Cómo encontrar su trámite'],
                ['titulo' => 'Otros servicios', 'texto' => 'Aquí puede validar un documento con su código, consultar documentos públicos y ver el manual del ciudadano.', 'sel' => '.portal-servicios', 'manual' => '7. Verificar que un documento es auténtico'],
                ['titulo' => 'Para hacer un trámite, ingrese', 'texto' => 'Su identidad la acredita ClaveÚnica. Sus datos no se pueden modificar y se usan solo para tramitar su solicitud.', 'sel' => '.sgd-topbar-der', 'manual' => '1. Cómo ingresar'],
                ['titulo' => 'Accesibilidad', 'texto' => 'Esta barra permite aumentar el texto, subir el contraste y ajustar la lectura.', 'sel' => '.a11y-quick', 'manual' => '8. Accesibilidad'],
            ],
            default => [
                ['titulo' => 'Su espacio de trabajo', 'texto' => 'Al ingresar ve su tablero y su menú, con solo las opciones que su rol permite.', 'ruta' => 'app.bandeja', 'sel' => '#contenido h1, .dash-kpis'],
                ['titulo' => 'Su menú', 'texto' => 'El menú lateral agrupa las pantallas de su rol. Los grupos se abren y se cierran.', 'sel' => '#navegacion'],
                ['titulo' => 'Su manual de usuario', 'texto' => 'Desde su nombre abre el manual de su perfil, que explica cada pantalla paso a paso y puede imprimirse o guardarse como PDF.', 'sel' => '.sgd-user'],
                ['titulo' => 'Accesibilidad', 'texto' => 'La barra superior permite ajustar el texto y el contraste.', 'sel' => '.a11y-quick'],
            ],
        };
    }

    /** Un expediente que la persona puede abrir (para mostrarlo en el recorrido), o null. */
    private static function expediente(?Usuario $u): ?int
    {
        static $cache = [];
        if (! $u || ! $u->unidad_id || ! $u->operaExpedientes()) {
            return null;
        }
        if (! array_key_exists($u->id, $cache)) {
            $e = Expediente::query()->where('unidad_id', $u->unidad_id)->where('estado', 'abierto')->latest('id')->first();
            $cache[$u->id] = $e && $u->can('ver', $e) ? $e->id : null;
        }

        return $cache[$u->id];
    }

    /**
     * Pasos listos para el navegador: dirección (ruta de la pantalla; un paso sin ruta usa la del anterior), selector y enlace al manual.
     *
     * @return list<array{titulo:string,texto:string,url:?string,sel:?string,manual:?string}>
     */
    public static function listos(string $perfil, ?Usuario $u = null): array
    {
        $manualDe = $perfil === 'publico' ? 'ciudadano' : $perfil; // el público solo accede al manual del ciudadano
        $ultima = null;
        $out = [];
        foreach (self::pasos($perfil, $u) as $p) {
            $r = $p['ruta'] ?? null;
            if ($r !== null) {
                $params = null;
                if (is_array($r)) {
                    [$r, $params] = $r;
                } elseif (str_contains($r, ':')) {
                    [$r, $params] = explode(':', $r, 2);
                }
                $ultima = Route::has($r) ? route($r, $params ?? [], false) : $ultima;
            }
            $manual = route('ayuda.manual', $manualDe).(isset($p['manual']) ? '#'.Str::slug($p['manual']) : '');

            $out[] = ['titulo' => $p['titulo'], 'texto' => $p['texto'], 'url' => $ultima, 'sel' => $p['sel'] ?? null, 'manual' => $manual];
        }

        return $out;
    }
}
