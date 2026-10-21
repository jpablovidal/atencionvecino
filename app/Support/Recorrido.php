<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Recorrido guiado por perfil: pasos breves con un enlace a la pantalla y a la sección del manual de usuario correspondiente
 * (el ancla es la del encabezado del manual, calculada igual que ManualRenderer). El texto describe solo lo que el sistema hace.
 */
class Recorrido
{
    /** @return list<array{titulo:string,texto:string,ir?:array{string,string},manual?:string}> ir = [ruta, etiqueta]; manual = encabezado del manual del perfil */
    public static function pasos(string $perfil): array
    {
        return match ($perfil) {
            'ciudadano' => [
                ['titulo' => 'Bienvenido(a) a su espacio de trámites', 'texto' => 'Desde aquí puede presentar solicitudes a la Municipalidad, seguir su avance y descargar las respuestas, sin ir a una oficina. Este recorrido dura un par de minutos.', 'manual' => 'Su página de inicio'],
                ['titulo' => 'Encuentre su trámite', 'texto' => 'Use el buscador o la lista de «Trámites y servicios»: cada ficha muestra qué es, quién lo atiende, cuánto demora y qué documentos debe adjuntar.', 'ir' => ['catalogo', 'Ver trámites y servicios'], 'manual' => 'Cómo encontrar su trámite'],
                ['titulo' => 'Ingrese con ClaveÚnica', 'texto' => 'Se usa su ClaveÚnica para acreditar quién es. Sus nombres y RUN vienen de ClaveÚnica y no se pueden modificar.', 'manual' => '1. Cómo ingresar'],
                ['titulo' => 'Complete su solicitud en 4 pasos', 'texto' => 'Sus datos y el detalle (hasta 2.000 caracteres, con contador) · los documentos (puede verlos antes de enviar, reemplazarlos o tomar una fotografía desde el teléfono) · cómo desea ser notificado(a) · revisar y enviar, indicando si autoriza el tratamiento de sus datos personales.', 'manual' => '2. Presentar una solicitud'],
                ['titulo' => 'Guarde su folio y comprobante', 'texto' => 'Al enviar recibe un número de solicitud (folio) y un comprobante en PDF con un código para verificarlo.', 'manual' => '3. Folio y comprobante'],
                ['titulo' => 'Siga el avance', 'texto' => 'En «Mis solicitudes» ve en qué etapa está, qué dirección la tiene y la fecha de cada movimiento. Le avisamos a su Casilla Única, con copia a su correo.', 'ir' => ['ciudadano.index', 'Ir a mis solicitudes'], 'manual' => '4. Seguimiento del estado'],
                ['titulo' => 'Sus datos y la accesibilidad', 'texto' => 'Puede ejercer sus derechos sobre sus datos personales y revisar la política de privacidad. La barra superior permite aumentar el texto, subir el contraste y ajustar la lectura.', 'ir' => ['privacidad', 'Privacidad y datos personales'], 'manual' => '8. Accesibilidad'],
            ],
            'funcionario' => [
                ['titulo' => 'Su bandeja de trabajo', 'texto' => 'Al ingresar ve las solicitudes que le corresponden y su tablero de inicio con lo pendiente.', 'ir' => ['app.bandeja', 'Ir a mi bandeja'], 'manual' => '1. Ingreso y bandeja'],
                ['titulo' => 'Tome la solicitud y cree el expediente', 'texto' => 'Al tomar una solicitud se crea el expediente con su código único y su índice electrónico.', 'manual' => '2. Tomar una solicitud y crear el expediente'],
                ['titulo' => 'Cargue archivos, comente, derive o devuelva', 'texto' => 'Todo queda registrado. Si falta información puede devolver la solicitud al vecino(a) con un plazo. Si el titular indicó que no autoriza el tratamiento de sus datos, verá un aviso en el expediente: trate solo lo necesario para resolver.', 'manual' => '3. Cargar archivos, comentar, derivar y devolver'],
                ['titulo' => 'Documentos del expediente', 'texto' => 'Redacte documentos con plantillas, envíelos a visación y firma, y consulte sus versiones.', 'manual' => '4. Documentos del expediente'],
                ['titulo' => 'Cierre y notifique', 'texto' => 'Al terminar, cierre el expediente y notifique la respuesta al vecino(a) por el canal elegido.', 'manual' => '5. Cerrar, reabrir y notificar'],
                ['titulo' => 'Comunicaciones oficiales', 'texto' => 'Las comunicaciones entre instituciones por DocDigital se gestionan desde su menú (si su rol lo permite).', 'manual' => '6. Comunicaciones oficiales por DocDigital'],
                ['titulo' => 'Atajos y accesibilidad', 'texto' => 'Hay atajos de teclado y opciones de lectura en la barra superior. El manual completo está siempre en «Manuales de usuario».', 'manual' => '10. Accesibilidad y atajos'],
            ],
            'administrador' => [
                ['titulo' => 'Menú del administrador', 'texto' => 'Su menú agrupa la administración, la seguridad y las integraciones. Un(a) superadministrador(a) puede abrir todas las vistas del sistema.', 'manual' => '1. Menú del administrador'],
                ['titulo' => 'Direcciones, departamentos y unidades', 'texto' => 'Las direcciones se crean aquí o se importan desde el directorio de funcionarios (Integraciones → Directorio → Importar), junto con sus departamentos y unidades.', 'ir' => ['app.admin.index:unidades', 'Ir a unidades'], 'manual' => '2. Unidades'],
                ['titulo' => 'Usuarios y roles', 'texto' => 'Cree usuarios, asígneles rol, dirección, departamento y unidad, y defina permisos por rol. Los funcionarios del directorio entran con ClaveÚnica.', 'ir' => ['app.admin.index:usuarios', 'Ir a usuarios'], 'manual' => '3. Usuarios'],
                ['titulo' => 'Trámites y servicios', 'texto' => 'Defina los trámites que ve la ciudadanía, sus anexos y la dirección responsable.', 'manual' => '4. Trámites y servicios'],
                ['titulo' => 'Integraciones', 'texto' => 'ClaveÚnica, FirmaGob, DocDigital, correo, IA, directorio y más se configuran y validan desde pantalla; las claves se guardan cifradas.', 'ir' => ['app.admin.api', 'Ir a APIs e integraciones'], 'manual' => '8. DocDigital'],
                ['titulo' => 'Seguridad, auditoría y datos personales', 'texto' => 'Revise la bitácora, las sesiones y los incidentes. El panel «Datos personales (DPO)» muestra las solicitudes cuyo titular no autorizó el tratamiento de sus datos.', 'ir' => ['app.datospersonales', 'Ir al panel de datos personales'], 'manual' => '10. Seguridad y auditoría'],
            ],
            'publico' => [
                ['titulo' => 'Bienvenido(a)', 'texto' => 'Sin iniciar sesión puede buscar trámites y servicios, validar documentos y consultar documentos públicos.', 'ir' => ['catalogo', 'Ver trámites y servicios'], 'manual' => 'Buscar trámites y servicios'],
                ['titulo' => 'Valide un documento', 'texto' => 'Con el código del documento puede comprobar que es auténtico.', 'ir' => ['validar.form', 'Validar un documento'], 'manual' => '1. Validar un documento'],
                ['titulo' => 'Accesibilidad', 'texto' => 'La barra superior permite aumentar el texto, subir el contraste y ajustar la lectura. Para hacer un trámite, ingrese con ClaveÚnica.', 'manual' => '4. Opciones de accesibilidad'],
            ],
            default => [
                ['titulo' => 'Su espacio de trabajo', 'texto' => 'Al ingresar ve su tablero y su menú, con solo las opciones que su rol permite.', 'ir' => ['app.bandeja', 'Ir a mi inicio']],
                ['titulo' => 'Su manual de usuario', 'texto' => 'El manual de su perfil explica cada pantalla paso a paso y puede imprimirse o guardarse como PDF.'],
                ['titulo' => 'Accesibilidad y ayuda', 'texto' => 'La barra superior permite ajustar el texto y el contraste. Los demás manuales de su perfil están en «Manuales de usuario».', 'ir' => ['accesibilidad', 'Declaración de accesibilidad']],
            ],
        };
    }

    /**
     * Pasos listos para mostrar: enlaces resueltos (solo rutas existentes) y ancla del manual.
     *
     * @return list<array{titulo:string,texto:string,ir:?array{string,string},manual:?string}>
     */
    public static function listos(string $perfil): array
    {
        return array_map(function (array $p) use ($perfil) {
            $ir = null;
            if (! empty($p['ir'])) {
                [$ruta, $et] = $p['ir'];
                [$nombre, $param] = array_pad(explode(':', $ruta, 2), 2, null);
                if (Route::has($nombre)) {
                    $ir = [$param !== null ? route($nombre, $param) : route($nombre), $et];
                }
            }
            $manual = route('ayuda.manual', $perfil).(isset($p['manual']) ? '#'.Str::slug($p['manual']) : '');

            return ['titulo' => $p['titulo'], 'texto' => $p['texto'], 'ir' => $ir, 'manual' => $manual];
        }, self::pasos($perfil));
    }
}
