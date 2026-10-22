<?php

use App\Http\Controllers\AdminConfigController;
use App\Http\Controllers\AnunciosController;
use App\Http\Controllers\AparienciaController;
use App\Http\Controllers\ApiConfigController;
use App\Http\Controllers\ArchivoMunicipalController;
use App\Http\Controllers\ArchivoVisorController;
use App\Http\Controllers\AreasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AyudaController;
use App\Http\Controllers\CasillaController;
use App\Http\Controllers\CatalogoTramitesController;
use App\Http\Controllers\CiudadanoController;
use App\Http\Controllers\CumplimientoController;
use App\Http\Controllers\DatosPersonalesController;
use App\Http\Controllers\DocDigitalController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DosFactoresController;
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ExpedienteOficioController;
use App\Http\Controllers\FeriadosController;
use App\Http\Controllers\FirmasController;
use App\Http\Controllers\FirmasMasivasController;
use App\Http\Controllers\FoliosController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\InteropController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\MantenedorController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PagosController;
use App\Http\Controllers\PermisosUsuarioController;
use App\Http\Controllers\PlantillasController;
use App\Http\Controllers\ProduccionController;
use App\Http\Controllers\ProveedoresController;
use App\Http\Controllers\PublicoController;
use App\Http\Controllers\RecursosController;
use App\Http\Controllers\RepositorioController;
use App\Http\Controllers\RespuestasController;
use App\Http\Controllers\RestringidosController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\SeguridadController;
use App\Http\Controllers\SesionesController;
use App\Http\Controllers\SistemaController;
use App\Http\Controllers\TramitesController;
use App\Http\Controllers\TransparenciaController;
use App\Http\Controllers\UsuariosController;
use App\Http\Controllers\VinculosController;
use Illuminate\Support\Facades\Route;

// ---- Público
Route::get('/', [PublicoController::class, 'inicio'])->name('inicio');
Route::middleware('throttle:publico')->group(function () {
    Route::get('/publico', [PublicoController::class, 'listado'])->name('publico.listado');
    Route::get('/tramites', [CatalogoTramitesController::class, 'index'])->name('catalogo');
    Route::get('/tramites/sugerencias', [CatalogoTramitesController::class, 'sugerencias'])->name('catalogo.sugerencias');
    Route::get('/tramites/{id}/iniciar', [CatalogoTramitesController::class, 'iniciar'])->whereNumber('id')->name('catalogo.iniciar');
    Route::get('/tramites/{id}/{slug?}', [CatalogoTramitesController::class, 'ficha'])->whereNumber('id')->where('slug', '[a-z0-9\-]+')->name('catalogo.ficha');
    Route::get('/pagos-proveedores', [ProveedoresController::class, 'form'])->name('proveedores.pagos');
    Route::get('/pagos-proveedores/ingresar', [ProveedoresController::class, 'ingresar'])->middleware('throttle:login')->name('proveedores.pagos.ingresar');
    Route::post('/pagos-proveedores', [ProveedoresController::class, 'consultar'])->middleware('throttle:12,1')->name('proveedores.pagos.consultar');
    Route::get('/validar', [PublicoController::class, 'formValidar'])->name('validar.form');
    Route::post('/validar', [PublicoController::class, 'buscarValidar'])->name('validar.buscar');
    Route::get('/validar/{codigo}', [PublicoController::class, 'validar'])->name('validar')->where('codigo', 'SDD-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}');
    Route::get('/validar/{codigo}/copia', [PublicoController::class, 'copia'])->name('validar.copia')->where('codigo', 'SDD-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}');
    Route::get('/validar-comprobante/{folio}/{codigo}', [PublicoController::class, 'validarComprobante'])->name('validar.comprobante');
});

// ---- Marca (públicas): CSS de colores y logo
Route::get('/anuncios/{anuncio}/medio', [AnunciosController::class, 'medio'])->whereNumber('anuncio')->middleware('throttle:120,1')->name('anuncios.medio');
Route::get('/marca/estilo.css', [MarcaController::class, 'css'])->name('marca.css');
Route::get('/marca/logo', [MarcaController::class, 'logo'])->name('marca.logo');

// ---- Ayuda y accesibilidad (públicas)
Route::get('/accesibilidad', [AyudaController::class, 'accesibilidad'])->name('accesibilidad');
Route::get('/privacidad', [AyudaController::class, 'privacidad'])->name('privacidad');
Route::get('/ayuda', [AyudaController::class, 'indice'])->name('ayuda');
Route::get('/ayuda/{perfil}', [AyudaController::class, 'manual'])->where('perfil', '[a-z\-]+')->name('ayuda.manual');

// ---- Autenticación (solo ClaveÚnica)
Route::get('/ingreso', [AuthController::class, 'ingreso'])->name('ingreso');
Route::get('/ingreso/pruebas', [AuthController::class, 'pruebasForm'])->name('ingreso.pruebas');
Route::post('/ingreso/pruebas', [AuthController::class, 'pruebasLogin'])->middleware('throttle:login')->name('ingreso.pruebas.entrar');
// Un solo ingreso con ClaveÚnica: que la persona sea funcionario(a) o vecino(a) lo decide el directorio (base «sistemas_usuarios») al volver de ClaveÚnica
Route::get('/login', [AuthController::class, 'redirigir'])->middleware('throttle:login')->name('login');
Route::get('/login/usuario', [AuthController::class, 'usuarioForm'])->name('login.usuario');
Route::post('/login/usuario', [AuthController::class, 'usuarioLogin'])->middleware('throttle:login')->name('login.usuario.entrar');
Route::permanentRedirect('/ingreso/ciudadano', '/login');
Route::permanentRedirect('/ingreso/funcionario', '/login');
Route::permanentRedirect('/ingreso/auto', '/login');
Route::get('/claveunica/callback', [AuthController::class, 'callback'])->middleware('throttle:login')->name('claveunica.callback'); // Redirect URI certificada por Gobierno Digital
Route::get('/auth/callback', [AuthController::class, 'callback'])->middleware('throttle:login'); // alias: solo sirve si se registrara ante ClaveÚnica
Route::get('/logout', [AuthController::class, 'retornoCierre'])->name('logout.retorno'); // Logout URI certificada: a donde vuelve ClaveÚnica tras cerrar su sesión
Route::post('/salir', [AuthController::class, 'salir'])->name('salir');
if (config('sgd.claveunica.mode') === 'mock' && app()->environment(['local', 'testing'])) { // jamás en staging/producción
    Route::get('/dev/login', [AuthController::class, 'devForm'])->name('dev.login');
    Route::post('/dev/login', [AuthController::class, 'devLogin'])->middleware('throttle:login');
}

// ---- Inicio dual (funcionario que también es vecino)
Route::get('/espacio', [EspacioController::class, 'inicio'])->middleware('nostore')->name('espacio');

// ---- Ciudadano
Route::prefix('ciudadano')->middleware(['ciudadano', 'nostore'])->name('ciudadano.')->group(function () {
    Route::get('/', [CiudadanoController::class, 'index'])->name('index');
    Route::get('/nueva/{tramite}', [CiudadanoController::class, 'nueva'])->name('nueva');
    Route::post('/solicitudes', [CiudadanoController::class, 'store'])->middleware('throttle:envio')->name('store');
    Route::get('/solicitudes/{folio}', [CiudadanoController::class, 'solicitud'])->name('solicitud');
    Route::post('/solicitudes/{folio}/consentimiento', [CiudadanoController::class, 'consentimiento'])->middleware('throttle:envio')->name('consentimiento');
    Route::post('/solicitudes/{folio}/responder', [CiudadanoController::class, 'responder'])->middleware('throttle:envio')->name('responder');
    Route::get('/solicitudes/{folio}/comprobante', [CiudadanoController::class, 'comprobante'])->name('comprobante');
    Route::get('/solicitudes/{folio}/documentos/{documento}', [CiudadanoController::class, 'documento'])->name('documento');
    Route::get('/solicitudes/{folio}/archivos/{archivo}', [CiudadanoController::class, 'archivoRespuesta'])->whereNumber('archivo')->name('archivo');
});

// ---- Funcionarios
Route::prefix('app')->middleware(['funcionario', 'nostore'])->name('app.')->group(function () {
    // Segundo factor del administrador (las rutas app.2fa* están exentas de la exigencia).
    Route::get('/2fa', [DosFactoresController::class, 'index'])->name('2fa');
    Route::post('/2fa', [DosFactoresController::class, 'verificar'])->middleware('throttle:login')->name('2fa.verificar');
    Route::post('/2fa/restablecer/{usuario}', [DosFactoresController::class, 'restablecer'])->whereNumber('usuario')->middleware('permiso:mantenedores.gestionar')->name('2fa.restablecer');
    Route::get('/', [FuncionarioController::class, 'bandeja'])->name('bandeja');
    Route::get('/vigentes', [FuncionarioController::class, 'vigentes'])->name('vigentes');
    Route::get('/firmas', [FirmasMasivasController::class, 'index'])->name('firmas');
    Route::post('/firmas/firmar', [FirmasMasivasController::class, 'firmar'])->middleware('throttle:firma')->name('firmas.firmar');
    Route::post('/firmas/visar', [FirmasMasivasController::class, 'visar'])->middleware('throttle:firma')->name('firmas.visar');
    Route::get('/alcaldia', [FuncionarioController::class, 'alcaldia'])->middleware('permiso:alcaldia.panel')->name('alcaldia');
    Route::get('/formatos', [PlantillasController::class, 'catalogo'])->name('formatos');
    Route::get('/formatos/{plantilla}', [PlantillasController::class, 'ver'])->whereNumber('plantilla')->name('formato');
    Route::get('/formatos/{plantilla}/pdf', [PlantillasController::class, 'pdf'])->whereNumber('plantilla')->name('formato.pdf');
    Route::post('/recursos', [RecursosController::class, 'subir'])->middleware('throttle:30,1')->name('recursos.subir');
    Route::get('/recursos/{recurso}', [RecursosController::class, 'mostrar'])->whereNumber('recurso')->name('recursos.mostrar');
    Route::get('/solicitudes/{folio}', [FuncionarioController::class, 'solicitud'])->name('solicitud');
    Route::post('/solicitudes/{folio}/asignar', [FuncionarioController::class, 'asignar'])->name('asignar');
    Route::get('/solicitudes/{folio}/archivos/{archivo}', [FuncionarioController::class, 'archivoSolicitud'])->name('solicitud.archivo');

    Route::get('/expedientes/nuevo', [ExpedienteOficioController::class, 'nuevo'])->name('expediente.nuevo');
    Route::post('/expedientes', [ExpedienteOficioController::class, 'crear'])->middleware('throttle:20,1')->name('expediente.crear');
    Route::post('/expedientes/{expediente}/partes', [ExpedienteOficioController::class, 'agregarParte'])->middleware('throttle:60,1')->name('expediente.partes.agregar');
    Route::post('/expedientes/{expediente}/partes/{parte}/quitar', [ExpedienteOficioController::class, 'quitarParte'])->whereNumber('parte')->name('expediente.partes.quitar');
    Route::post('/expedientes/{expediente}/notificar-partes', [ExpedienteOficioController::class, 'notificar'])->middleware('throttle:30,1')->name('expediente.partes.notificar');
    Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show'])->name('expediente');
    Route::post('/expedientes/{expediente}/metadatos', [ExpedienteController::class, 'metadatos'])->name('expediente.metadatos');
    Route::post('/expedientes/{expediente}/derivaciones/{derivacion}/estado', [ExpedienteController::class, 'estadoDerivacion'])->whereNumber('derivacion')->middleware('throttle:60,1')->name('expediente.derivacion.estado');
    Route::post('/expedientes/{expediente}/comentarios', [ExpedienteController::class, 'comentar'])->name('expediente.comentar');
    Route::post('/expedientes/{expediente}/devolver', [ExpedienteController::class, 'devolver'])->name('expediente.devolver');
    Route::post('/expedientes/{expediente}/derivar', [ExpedienteController::class, 'derivar'])->name('expediente.derivar');
    Route::post('/expedientes/{expediente}/archivos', [ExpedienteController::class, 'subir'])->name('expediente.subir');
    Route::get('/expedientes/{expediente}/archivos/{archivo}', [ExpedienteController::class, 'archivo'])->name('expediente.archivo');
    Route::get('/expedientes/{expediente}/archivos/{archivo}/ver/{numero?}', [ArchivoVisorController::class, 'ver'])->whereNumber('numero')->name('expediente.archivo.ver');
    Route::get('/expedientes/{expediente}/archivos/{archivo}/versiones', [ArchivoVisorController::class, 'historial'])->name('expediente.archivo.versiones');
    Route::get('/expedientes/{expediente}/archivos/{archivo}/versiones/{numero}/descargar', [ArchivoVisorController::class, 'descargarVersion'])->whereNumber('numero')->name('expediente.archivo.version.descargar');
    Route::post('/expedientes/{expediente}/archivos/{archivo}/versiones', [ArchivoVisorController::class, 'nuevaVersion'])->middleware('throttle:30,1')->name('expediente.archivo.version.nueva');
    Route::get('/expedientes/{expediente}/documentos/{documento}/historial', [ArchivoVisorController::class, 'documentoHistorial'])->name('expediente.documento.historial');
    Route::post('/expedientes/{expediente}/cerrar', [ExpedienteController::class, 'cerrar'])->name('expediente.cerrar');
    Route::post('/expedientes/{expediente}/reabrir', [ExpedienteController::class, 'reabrir'])->name('expediente.reabrir');
    Route::get('/expedientes/{expediente}/exportar', [ExpedienteController::class, 'exportar'])->name('expediente.exportar');
    Route::post('/expedientes/{expediente}/notificar', [ExpedienteController::class, 'notificar'])->name('expediente.notificar');
    Route::post('/notificaciones/{notificacion}/evidencia', [ExpedienteController::class, 'evidencia'])->name('notificacion.evidencia');

    Route::post('/expedientes/{expediente}/documentos', [DocumentoController::class, 'store'])->name('documento.store');
    Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])->name('documento');
    Route::post('/documentos/{documento}', [DocumentoController::class, 'guardar'])->name('documento.guardar');
    Route::post('/documentos/{documento}/flujo', [DocumentoController::class, 'flujo'])->name('documento.flujo');
    Route::post('/documentos/{documento}/visar', [DocumentoController::class, 'visar'])->name('documento.visar');
    Route::post('/documentos/{documento}/firmar', [DocumentoController::class, 'firmar'])->middleware('throttle:firma')->name('documento.firmar');
    Route::post('/documentos/{documento}/finalizar', [DocumentoController::class, 'finalizar'])->name('documento.finalizar');
    Route::get('/documentos/{documento}/pdf', [DocumentoController::class, 'pdf'])->name('documento.pdf');
    Route::post('/llm/borrador', [DocumentoController::class, 'llm'])->middleware('throttle:llm')->name('llm');

    // Interoperabilidad: PISEE, DocDigital, Codificador
    Route::post('/expedientes/{expediente}/pisee', [InteropController::class, 'pisee'])->middleware('throttle:interop')->name('pisee.consultar');
    Route::post('/expedientes/{expediente}/comunicaciones', [InteropController::class, 'comunicar'])->middleware('throttle:interop')->name('comunicacion.crear');
    Route::post('/comunicaciones/{comunicacion}/evidencia', [InteropController::class, 'evidencia'])->name('comunicacion.evidencia');
    Route::post('/comunicaciones/{comunicacion}/devolver', [InteropController::class, 'devolver'])->middleware('throttle:interop')->name('comunicacion.devolver');
    Route::post('/comunicaciones/{comunicacion}/gestion', [InteropController::class, 'gestion'])->middleware('throttle:interop')->name('comunicacion.gestion');
    Route::post('/comunicaciones/{comunicacion}/acuse', [InteropController::class, 'acuse'])->middleware('throttle:interop')->name('comunicacion.acuse');
    Route::post('/expedientes/{expediente}/asignarme', [ExpedienteController::class, 'asignarme'])->name('expediente.asignarme');
    Route::post('/comunicaciones/{comunicacion}/reintentar', [InteropController::class, 'reintentar'])->name('comunicacion.reintentar');
    Route::get('/comunicaciones', [InteropController::class, 'bandeja'])->name('comunicaciones');
    Route::get('/comunicaciones/{comunicacion}/archivo', [InteropController::class, 'archivo'])->name('comunicacion.archivo');

    Route::middleware('permiso:seguridad.ver,logs.ver')->group(function () {
        Route::get('/seguridad', [SeguridadController::class, 'panel'])->name('seguridad');
        Route::post('/seguridad/verificar', [SeguridadController::class, 'verificar'])->name('seguridad.verificar');
        Route::post('/seguridad/incidentes', [SeguridadController::class, 'incidente'])->name('seguridad.incidente');
        Route::post('/seguridad/incidentes/{incidente}', [SeguridadController::class, 'actualizarIncidente'])->name('seguridad.incidente.actualizar');
    });
    Route::middleware('permiso:logs.ver')->group(function () {
        Route::get('/logs', [LogsController::class, 'index'])->name('logs');
        Route::get('/logs/exportar', [LogsController::class, 'exportar'])->name('logs.exportar');
        Route::get('/logs/aplicacion', [LogsController::class, 'aplicacion'])->name('logs.aplicacion');
    });
    Route::get('/sesiones', [SesionesController::class, 'index'])->middleware('permiso:sesiones.ver')->name('sesiones');
    Route::post('/sesiones/{id}/cerrar', [SesionesController::class, 'cerrar'])->middleware('permiso:sesiones.cerrar')->name('sesiones.cerrar');
    Route::get('/sistema/conexiones', [SistemaController::class, 'conexiones'])->middleware('permiso:conexiones.ver')->name('sistema.conexiones');
    Route::get('/sistema/recursos', [SistemaController::class, 'recursos'])->middleware('permiso:recursos.ver')->name('sistema.recursos');
    Route::middleware('permiso:componentes.ver')->group(function () {
        Route::get('/sistema/componentes', [SistemaController::class, 'componentes'])->name('sistema.componentes');
        Route::get('/sistema/componentes.json', [SistemaController::class, 'exportar'])->name('sistema.componentes.json');
    });
    Route::get('/diagnostico', [SeguridadController::class, 'diagnostico'])->middleware('permiso:diagnostico.ver')->name('diagnostico');

    Route::get('/delegaciones', [MantenedorController::class, 'delegaciones'])->middleware('permiso:delegaciones.ver')->name('delegaciones');
    Route::post('/delegaciones', [MantenedorController::class, 'crearDelegacion'])->middleware('permiso:delegaciones.crear')->name('delegaciones.crear');
    Route::post('/delegaciones/{delegacion}/revocar', [MantenedorController::class, 'revocarDelegacion'])->middleware('permiso:delegaciones.crear')->name('delegaciones.revocar');

    // ---- Trámites y servicios por dirección (el ámbito se aplica en TramiteService)
    Route::prefix('tramites')->name('tramites.')->group(function () {
        Route::get('/', [TramitesController::class, 'index'])->name('index');
        Route::get('/nuevo', [TramitesController::class, 'crear'])->name('crear');
        Route::post('/', [TramitesController::class, 'guardar'])->name('guardar');
        Route::get('/{id}', [TramitesController::class, 'editar'])->whereNumber('id')->name('editar');
        Route::post('/{id}', [TramitesController::class, 'actualizar'])->whereNumber('id')->name('actualizar');
        Route::get('/{id}/vista', [TramitesController::class, 'vista'])->whereNumber('id')->name('vista');
        Route::get('/{id}/historial', [TramitesController::class, 'historial'])->whereNumber('id')->name('historial');
        Route::post('/{id}/publicar', [TramitesController::class, 'publicar'])->whereNumber('id')->name('publicar');
        Route::post('/{id}/deshabilitar', [TramitesController::class, 'deshabilitar'])->whereNumber('id')->name('deshabilitar');
        Route::post('/{id}/duplicar', [TramitesController::class, 'duplicar'])->whereNumber('id')->name('duplicar');
    });

    // ---- DocDigital: panel de gestión (ver = comunicaciones.ver; probar/sincronizar/reintentar = docdigital.gestionar)
    Route::get('/docdigital', [DocDigitalController::class, 'index'])->middleware('permiso:comunicaciones.ver,docdigital.gestionar')->name('docdigital');
    Route::get('/instituciones/buscar', [InteropController::class, 'buscarInstituciones'])->middleware(['permiso:comunicaciones.despachar', 'throttle:90,1'])->name('instituciones.buscar');
    Route::get('/docdigital/directorio/buscar', [DocDigitalController::class, 'buscarDirectorio'])->middleware(['permiso:comunicaciones.despachar,docdigital.gestionar', 'throttle:90,1'])->name('docdigital.directorio.buscar');
    Route::middleware('permiso:docdigital.gestionar')->group(function () {
        Route::get('/docdigital/entidades', [DocDigitalController::class, 'entidades'])->middleware('throttle:20,1')->name('docdigital.entidades');
        Route::get('/docdigital/directorio', [DocDigitalController::class, 'directorio'])->middleware('throttle:60,1')->name('docdigital.directorio');
        Route::post('/docdigital/directorio/cargar', [DocDigitalController::class, 'cargarDirectorio'])->middleware('throttle:6,1')->name('docdigital.directorio.cargar');
        Route::post('/docdigital/diagnosticar', [DocDigitalController::class, 'diagnosticar'])->middleware('throttle:10,1')->name('docdigital.diagnosticar');
        Route::post('/docdigital/sincronizar', [DocDigitalController::class, 'sincronizar'])->middleware('throttle:6,1')->name('docdigital.sincronizar');
        Route::post('/docdigital/evidencia', [DocDigitalController::class, 'evidencia'])->middleware('throttle:20,1')->name('docdigital.evidencia');
        Route::get('/docdigital/evidencia/descargar', [DocDigitalController::class, 'evidenciaDescargar'])->middleware('throttle:10,1')->name('docdigital.evidencia.descargar');
        Route::post('/docdigital/reintentar', [DocDigitalController::class, 'reintentar'])->middleware('throttle:6,1')->name('docdigital.reintentar');
    });

    // ---- Seguimiento de solicitudes (solo lectura): autoridad y OIRS; cada ruta valida su permiso en el controlador
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/seguimiento', [SeguimientoController::class, 'index'])->middleware('permiso:seguimiento.consultar')->name('seguimiento');
        Route::get('/oirs', [SeguimientoController::class, 'oirs'])->middleware('permiso:oirs.ver')->name('oirs');
        Route::get('/seguimiento/{folio}', [SeguimientoController::class, 'detalle'])->middleware('permiso:seguimiento.consultar,oirs.ver')->where('folio', '[A-Za-z0-9\-]{5,30}')->name('seguimiento.detalle');
    });

    // ---- Asociar otros expedientes y microformas (con su contenido) al expediente que se gestiona
    Route::middleware(['permiso:expedientes.vincular', 'throttle:60,1'])->group(function () {
        Route::get('/expedientes/{expediente}/asociar', [VinculosController::class, 'buscar'])->name('vinculos.buscar');
        Route::post('/expedientes/{expediente}/vinculos', [VinculosController::class, 'vincular'])->name('vinculos.crear');
        Route::get('/vinculos/{vinculo}', [VinculosController::class, 'ver'])->whereNumber('vinculo')->name('vinculos.ver');
        Route::post('/vinculos/{vinculo}/incorporar', [VinculosController::class, 'incorporar'])->whereNumber('vinculo')->name('vinculos.incorporar');
        Route::post('/vinculos/{vinculo}/quitar', [VinculosController::class, 'quitar'])->whereNumber('vinculo')->name('vinculos.quitar');
    });

    // ---- Decreto de pago y egreso de Tesorería
    Route::middleware('throttle:60,1')->prefix('pagos')->group(function () {
        Route::get('/', [PagosController::class, 'index'])->middleware('permiso:decretopago.ver')->name('pagos');
        Route::get('/tablero', [PagosController::class, 'tablero'])->middleware('permiso:decretopago.ver')->name('pagos.tablero');
        Route::get('/tablero/exportar', [PagosController::class, 'tableroCsv'])->middleware(['permiso:decretopago.ver', 'throttle:10,1'])->name('pagos.tablero.csv');
        Route::get('/nuevo', [PagosController::class, 'nuevo'])->middleware('permiso:decretopago.gestionar')->name('pagos.nuevo');
        Route::post('/', [PagosController::class, 'crear'])->middleware('permiso:decretopago.gestionar')->name('pagos.crear');
        Route::middleware('permiso:decretopago.configurar')->prefix('configuracion')->group(function () {
            Route::get('/', [PagosController::class, 'configuracion'])->name('pagos.config');
            Route::post('/{doc}/correlativo', [PagosController::class, 'guardarCorrelativo'])->where('doc', 'decreto_pago|egreso')->name('pagos.config.correlativo');
            Route::post('/{doc}/ajustar', [PagosController::class, 'ajustarContador'])->where('doc', 'decreto_pago|egreso')->name('pagos.config.ajustar');
            Route::post('/{doc}/firmantes', [PagosController::class, 'agregarFirmante'])->where('doc', 'decreto_pago|egreso')->name('pagos.config.firmante');
            Route::post('/firmantes/{firmante}/{accion}', [PagosController::class, 'accionFirmante'])->whereNumber('firmante')->name('pagos.config.accion');
        });
        Route::middleware('permiso:decretopago.ver')->group(function () {
            Route::get('/egresos/{egreso}', [PagosController::class, 'egreso'])->whereNumber('egreso')->name('pagos.egreso');
            Route::get('/egresos/{egreso}/pdf', [PagosController::class, 'egresoPdf'])->whereNumber('egreso')->name('pagos.egreso.pdf');
            Route::get('/{decreto}', [PagosController::class, 'ver'])->whereNumber('decreto')->name('pagos.ver');
            Route::get('/{decreto}/pdf', [PagosController::class, 'pdf'])->whereNumber('decreto')->name('pagos.pdf');
        });
        Route::middleware('permiso:decretopago.gestionar')->group(function () {
            Route::post('/{decreto}', [PagosController::class, 'actualizar'])->whereNumber('decreto')->name('pagos.actualizar');
            Route::post('/{decreto}/generar', [PagosController::class, 'generar'])->whereNumber('decreto')->name('pagos.generar');
            Route::post('/{decreto}/enviar-firma', [PagosController::class, 'enviarFirma'])->whereNumber('decreto')->name('pagos.enviar_firma');
            Route::post('/{decreto}/cargar', [PagosController::class, 'cargar'])->middleware('throttle:20,1')->whereNumber('decreto')->name('pagos.cargar');
            Route::post('/{decreto}/anular', [PagosController::class, 'anular'])->whereNumber('decreto')->name('pagos.anular');
        });
        Route::middleware('permiso:egresos.gestionar')->group(function () {
            Route::post('/{decreto}/egresos', [PagosController::class, 'crearEgreso'])->whereNumber('decreto')->name('pagos.egreso.crear');
            Route::post('/egresos/{egreso}/firmar', [PagosController::class, 'firmarEgreso'])->whereNumber('egreso')->name('pagos.egreso.firmar');
            Route::post('/egresos/{egreso}/anular', [PagosController::class, 'anularEgreso'])->whereNumber('egreso')->name('pagos.egreso.anular');
        });
    });

    // ---- Respuesta final de notificación: instruir (gestión del expediente) y despachar (Oficina de Partes)
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/expedientes/{expediente}/respuesta-final', [RespuestasController::class, 'instruir'])->middleware('permiso:respuestas.instruir')->name('respuestas.instruir');
        Route::post('/respuestas/{respuesta}/anular', [RespuestasController::class, 'anular'])->middleware('permiso:respuestas.instruir,respuestas.despachar')->whereNumber('respuesta')->name('respuestas.anular');
        Route::middleware('permiso:respuestas.despachar')->prefix('respuestas')->group(function () {
            Route::get('/', [RespuestasController::class, 'bandeja'])->name('respuestas');
            Route::get('/{respuesta}', [RespuestasController::class, 'ver'])->whereNumber('respuesta')->name('respuestas.ver');
            Route::post('/{respuesta}/despachar', [RespuestasController::class, 'despachar'])->whereNumber('respuesta')->name('respuestas.despachar');
            Route::post('/{respuesta}/reintentar', [RespuestasController::class, 'reintentar'])->whereNumber('respuesta')->name('respuestas.reintentar');
            Route::post('/{respuesta}/evidencia/{notificacion}', [RespuestasController::class, 'evidencia'])->whereNumber(['respuesta', 'notificacion'])->name('respuestas.evidencia');
            Route::get('/{respuesta}/items/{item}', [RespuestasController::class, 'item'])->whereNumber(['respuesta', 'item'])->name('respuestas.item');
        });
    });

    // ---- Anuncios del modal de bienvenida
    Route::middleware(['permiso:anuncios.gestionar', 'throttle:60,1'])->prefix('anuncios')->group(function () {
        Route::get('/', [AnunciosController::class, 'index'])->name('anuncios');
        Route::get('/nuevo', [AnunciosController::class, 'nuevo'])->name('anuncios.nuevo');
        Route::post('/', [AnunciosController::class, 'guardar'])->name('anuncios.crear');
        Route::get('/{anuncio}/editar', [AnunciosController::class, 'editar'])->whereNumber('anuncio')->name('anuncios.editar');
        Route::post('/{anuncio}', [AnunciosController::class, 'guardar'])->whereNumber('anuncio')->name('anuncios.actualizar');
        Route::post('/{anuncio}/alternar', [AnunciosController::class, 'alternar'])->whereNumber('anuncio')->name('anuncios.alternar');
        Route::post('/{anuncio}/eliminar', [AnunciosController::class, 'eliminar'])->whereNumber('anuncio')->name('anuncios.eliminar');
    });

    // ---- Restricciones de acceso (protección de datos personales)
    Route::get('/datos-personales', [DatosPersonalesController::class, 'index'])->middleware(['permiso:datospersonales.ver', 'throttle:60,1'])->name('datospersonales');

    Route::middleware(['throttle:60,1'])->prefix('restringidos')->group(function () {
        Route::get('/', [RestringidosController::class, 'accesos'])->middleware('permiso:restringidos.auditar')->name('restringidos');
        Route::middleware('permiso:restringidos.gestionar')->group(function () {
            Route::post('/expedientes/{expediente}/restringir', [RestringidosController::class, 'restringir'])->name('restringidos.restringir');
            Route::post('/expedientes/{expediente}/liberar', [RestringidosController::class, 'liberar'])->name('restringidos.liberar');
            Route::post('/expedientes/{expediente}/autorizar', [RestringidosController::class, 'autorizar'])->name('restringidos.autorizar');
            Route::post('/autorizaciones/{autorizacion}/revocar', [RestringidosController::class, 'revocar'])->whereNumber('autorizacion')->name('restringidos.revocar');
        });
    });

    // ---- Configuración del administrador: archivos, áreas, codificación y normas técnicas
    Route::middleware(['permiso:archivos.configurar', 'throttle:60,1'])->prefix('admin/archivos')->name('admin.archivos')->group(function () {
        Route::get('/', [AdminConfigController::class, 'archivosForm']);
        Route::post('/', [AdminConfigController::class, 'archivosGuardar'])->name('.guardar');
    });
    Route::middleware(['permiso:areas.gestionar', 'throttle:60,1'])->prefix('admin/areas')->name('admin.areas')->group(function () {
        Route::get('/', [AreasController::class, 'index']);
        Route::post('/', [AreasController::class, 'guardar'])->name('.guardar');
    });
    Route::middleware(['permiso:codificacion.configurar', 'throttle:60,1'])->prefix('admin/codificacion')->name('admin.codificacion')->group(function () {
        Route::get('/', [AdminConfigController::class, 'codificacion']);
        Route::post('/municipio', [AdminConfigController::class, 'elegirMunicipio'])->name('.municipio');
        Route::post('/patrones', [AdminConfigController::class, 'guardarPatrones'])->name('.patrones');
    });

    Route::middleware(['permiso:produccion.gestionar', 'throttle:20,1'])->prefix('admin/produccion')->name('admin.produccion')->group(function () {
        Route::get('/', [ProduccionController::class, 'index']);
        Route::post('/simular', [ProduccionController::class, 'simular'])->middleware('permiso:simulacion.gestionar')->name('.simular');
        Route::post('/pasar', [ProduccionController::class, 'pasar'])->name('.pasar');
    });

    // ---- Administrador de feriados (no cuentan como días administrativos en los plazos)
    Route::middleware(['permiso:feriados.gestionar', 'throttle:60,1'])->prefix('feriados')->group(function () {
        Route::get('/', [FeriadosController::class, 'index'])->name('feriados');
        Route::post('/', [FeriadosController::class, 'guardar'])->name('feriados.guardar');
        Route::post('/importar', [FeriadosController::class, 'importar'])->name('feriados.importar');
        Route::post('/{feriado}', [FeriadosController::class, 'actualizar'])->whereNumber('feriado')->name('feriados.actualizar');
        Route::post('/{feriado}/eliminar', [FeriadosController::class, 'eliminar'])->whereNumber('feriado')->name('feriados.eliminar');
    });

    // ---- Transparencia activa y pasiva (Ley 20.285): cada ruta exige su permiso
    Route::middleware('throttle:60,1')->group(function () {
        $folio = '[A-Za-z0-9\-]{5,30}';
        Route::get('/transparencia', [TransparenciaController::class, 'panel'])->middleware('permiso:transparencia.ver')->name('transparencia');
        Route::get('/transparencia/requerimientos', [TransparenciaController::class, 'requerimientos'])->middleware('permiso:transparencia.ver,transparencia.responder')->name('transparencia.requerimientos');
        Route::post('/transparencia/requerimientos/{requerimiento}/responder', [TransparenciaController::class, 'responder'])->middleware('permiso:transparencia.responder')->whereNumber('requerimiento')->name('transparencia.responder');
        Route::get('/transparencia/activa', [TransparenciaController::class, 'activa'])->middleware('permiso:transparencia.ver')->name('transparencia.activa');
        Route::get('/transparencia/plazos', [TransparenciaController::class, 'plazos'])->middleware('permiso:transparencia.ver')->name('transparencia.plazos');
        Route::get('/transparencia/reportes', [TransparenciaController::class, 'reportes'])->middleware('permiso:transparencia.reportes')->name('transparencia.reportes');
        Route::middleware('permiso:transparencia.gestionar')->group(function () use ($folio) {
            Route::post('/transparencia/solicitudes/{folio}/prorroga', [TransparenciaController::class, 'prorrogar'])->where('folio', $folio)->name('transparencia.prorroga');
            Route::post('/transparencia/solicitudes/{folio}/requerimientos', [TransparenciaController::class, 'requerir'])->where('folio', $folio)->name('transparencia.requerir');
            Route::post('/transparencia/solicitudes/{folio}/derivar-organo', [TransparenciaController::class, 'derivarOrgano'])->where('folio', $folio)->name('transparencia.derivar_organo');
            Route::post('/transparencia/activa', [TransparenciaController::class, 'activaGuardar'])->name('transparencia.activa.crear');
            Route::post('/transparencia/activa/{item}', [TransparenciaController::class, 'activaGuardar'])->whereNumber('item')->name('transparencia.activa.guardar');
            Route::post('/transparencia/activa/{item}/registrar', [TransparenciaController::class, 'activaRegistrar'])->whereNumber('item')->name('transparencia.activa.registrar');
            Route::post('/transparencia/plazos', [TransparenciaController::class, 'guardarParametros'])->name('transparencia.plazos.guardar');
        });
    });

    // ---- Casilla Única: panel de notificaciones (ver = notificaciones.gestionar o casilla.gestionar; probar/reintentar = casilla.gestionar)
    Route::get('/casilla', [CasillaController::class, 'index'])->middleware('permiso:notificaciones.gestionar,casilla.gestionar')->name('casilla');
    Route::middleware('permiso:casilla.gestionar')->group(function () {
        Route::post('/casilla/diagnosticar', [CasillaController::class, 'diagnosticar'])->middleware('throttle:10,1')->name('casilla.diagnosticar');
        Route::post('/casilla/avisos/{aviso}/evidencia', [CasillaController::class, 'avisoEvidencia'])->whereNumber('aviso')->name('casilla.aviso_evidencia');
        Route::post('/casilla/reintentar', [CasillaController::class, 'reintentar'])->middleware('throttle:6,1')->name('casilla.reintentar');
    });

    // ---- Archivo municipal (microformas): permisos por ruta y de nuevo en el servicio
    Route::prefix('archivo')->name('archivo.')->group(function () {
        Route::middleware('permiso:archivo.ver')->group(function () {
            Route::get('/', [ArchivoMunicipalController::class, 'index'])->name('index');
            Route::get('/{id}', [ArchivoMunicipalController::class, 'ver'])->whereNumber('id')->name('ver');
            Route::get('/{id}/archivo/{archivo}', [ArchivoMunicipalController::class, 'archivo'])->whereNumber(['id', 'archivo'])->middleware('throttle:60,1')->name('archivo');
            Route::get('/{id}/constancia', [ArchivoMunicipalController::class, 'constancia'])->whereNumber('id')->middleware('throttle:20,1')->name('constancia');
            Route::post('/{id}/verificar', [ArchivoMunicipalController::class, 'verificar'])->whereNumber('id')->middleware('throttle:20,1')->name('verificar');
        });
        Route::middleware('permiso:archivo.gestionar')->group(function () {
            Route::get('/nueva', [ArchivoMunicipalController::class, 'crear'])->name('crear');
            Route::post('/', [ArchivoMunicipalController::class, 'guardar'])->name('guardar');
            Route::post('/{id}', [ArchivoMunicipalController::class, 'actualizar'])->whereNumber('id')->name('actualizar');
            Route::post('/{id}/subir', [ArchivoMunicipalController::class, 'subir'])->whereNumber('id')->middleware('throttle:30,1')->name('subir');
            Route::post('/{id}/archivo/{archivo}/quitar', [ArchivoMunicipalController::class, 'quitar'])->whereNumber(['id', 'archivo'])->name('quitar');
            Route::post('/{id}/indice', [ArchivoMunicipalController::class, 'itemAgregar'])->whereNumber('id')->name('item.agregar');
            Route::post('/{id}/indice/{item}/quitar', [ArchivoMunicipalController::class, 'itemQuitar'])->whereNumber(['id', 'item'])->name('item.quitar');
            Route::post('/{id}/enviar', [ArchivoMunicipalController::class, 'enviar'])->whereNumber('id')->name('enviar');
            Route::post('/{id}/anular', [ArchivoMunicipalController::class, 'anular'])->whereNumber('id')->name('anular');
            Route::post('/{id}/disposicion', [ArchivoMunicipalController::class, 'disposicionSolicitar'])->whereNumber('id')->name('disposicion.solicitar');
            Route::post('/{id}/disposicion/ejecutar', [ArchivoMunicipalController::class, 'disposicionEjecutar'])->whereNumber('id')->name('disposicion.ejecutar');
        });
        Route::middleware('permiso:archivo.certificar')->group(function () {
            Route::post('/{id}/certificar', [ArchivoMunicipalController::class, 'certificar'])->whereNumber('id')->middleware('throttle:20,1')->name('certificar');
            Route::post('/{id}/devolver', [ArchivoMunicipalController::class, 'devolver'])->whereNumber('id')->name('devolver');
        });
        Route::post('/{id}/disposicion/resolver', [ArchivoMunicipalController::class, 'disposicionResolver'])->whereNumber('id')->middleware('permiso:archivo.disposicion')->name('disposicion.resolver');
    });

    // ---- Administración (módulos específicos ANTES del mantenedor genérico /{entidad})
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permiso:apariencia.gestionar')->group(function () {
            Route::get('/apariencia', [AparienciaController::class, 'index'])->name('apariencia');
            Route::post('/apariencia', [AparienciaController::class, 'guardar'])->name('apariencia.guardar');
            Route::post('/apariencia/logo', [AparienciaController::class, 'logo'])->name('apariencia.logo');
            Route::post('/apariencia/logo/quitar', [AparienciaController::class, 'quitarLogo'])->name('apariencia.logo.quitar');
            Route::post('/apariencia/restablecer', [AparienciaController::class, 'restablecer'])->name('apariencia.restablecer');
        });
        Route::middleware('permiso:cumplimiento.ver')->prefix('cumplimiento')->group(function () {
            Route::get('/', [CumplimientoController::class, 'index'])->name('cumplimiento');
            Route::get('/informe', [CumplimientoController::class, 'informe'])->name('cumplimiento.informe');
            Route::middleware(['permiso:cumplimiento.gestionar', 'throttle:60,1'])->group(function () {
                Route::post('/politica', [CumplimientoController::class, 'politica'])->name('cumplimiento.politica');
                Route::post('/seguridad', [CumplimientoController::class, 'seguridad'])->name('cumplimiento.seguridad');
                Route::post('/coordinador', [CumplimientoController::class, 'coordinador'])->name('cumplimiento.coordinador');
                Route::get('/plataformas/nueva', [CumplimientoController::class, 'plataforma'])->name('cumplimiento.plataforma.nueva');
                Route::get('/plataformas/{plataforma}', [CumplimientoController::class, 'plataforma'])->whereNumber('plataforma')->name('cumplimiento.plataforma');
                Route::post('/plataformas', [CumplimientoController::class, 'guardarPlataforma'])->name('cumplimiento.plataforma.guardar');
                Route::post('/plataformas/{plataforma}', [CumplimientoController::class, 'guardarPlataforma'])->whereNumber('plataforma')->name('cumplimiento.plataforma.actualizar');
                Route::get('/iniciativas/nueva', [CumplimientoController::class, 'iniciativa'])->name('cumplimiento.iniciativa.nueva');
                Route::get('/iniciativas/{iniciativa}', [CumplimientoController::class, 'iniciativa'])->whereNumber('iniciativa')->name('cumplimiento.iniciativa');
                Route::post('/iniciativas', [CumplimientoController::class, 'guardarIniciativa'])->name('cumplimiento.iniciativa.guardar');
                Route::post('/iniciativas/{iniciativa}', [CumplimientoController::class, 'guardarIniciativa'])->whereNumber('iniciativa')->name('cumplimiento.iniciativa.actualizar');
            });
        });
        Route::middleware('permiso:roles.gestionar')->group(function () {
            Route::get('/permisos', [PermisosUsuarioController::class, 'indice'])->name('permisos');
            Route::get('/permisos/matriz.csv', [PermisosUsuarioController::class, 'csv'])->middleware('throttle:10,1')->name('permisos.csv');
            Route::get('/permisos/{usuario}', [PermisosUsuarioController::class, 'detalle'])->whereNumber('usuario')->name('permisos.usuario');
            Route::post('/permisos/{usuario}', [PermisosUsuarioController::class, 'guardar'])->whereNumber('usuario')->name('permisos.guardar');
            Route::get('/roles', [RolesController::class, 'index'])->name('roles');
            Route::get('/roles/nuevo', [RolesController::class, 'crear'])->name('roles.crear');
            Route::post('/roles', [RolesController::class, 'guardar'])->name('roles.guardar');
            Route::get('/roles/{rol}', [RolesController::class, 'editar'])->name('roles.editar');
            Route::post('/roles/{rol}', [RolesController::class, 'guardar'])->name('roles.actualizar');
            Route::post('/roles/{rol}/estado', [RolesController::class, 'alternar'])->name('roles.alternar');
            Route::post('/roles/{rol}/eliminar', [RolesController::class, 'eliminar'])->name('roles.eliminar');
        });
        Route::middleware('permiso:plantillas.gestionar')->group(function () {
            Route::get('/plantillas', [PlantillasController::class, 'index'])->name('plantillas');
            Route::get('/plantillas/nuevo', [PlantillasController::class, 'crear'])->name('plantillas.crear');
            Route::post('/plantillas', [PlantillasController::class, 'guardar'])->name('plantillas.guardar');
            Route::get('/plantillas/{plantilla}', [PlantillasController::class, 'editar'])->whereNumber('plantilla')->name('plantillas.editar');
            Route::post('/plantillas/{plantilla}', [PlantillasController::class, 'guardar'])->whereNumber('plantilla')->name('plantillas.actualizar');
            Route::post('/plantillas/{plantilla}/estado', [PlantillasController::class, 'alternar'])->whereNumber('plantilla')->name('plantillas.alternar');
            Route::post('/plantillas/{plantilla}/duplicar', [PlantillasController::class, 'duplicar'])->whereNumber('plantilla')->name('plantillas.duplicar');
            Route::get('/plantillas/{plantilla}/versiones', [PlantillasController::class, 'versiones'])->whereNumber('plantilla')->name('plantillas.versiones');
            Route::get('/plantillas/{plantilla}/versiones/{version}', [PlantillasController::class, 'verVersion'])->whereNumber(['plantilla', 'version'])->name('plantillas.version');
            Route::post('/plantillas/{plantilla}/versiones/{version}/restaurar', [PlantillasController::class, 'restaurar'])->whereNumber(['plantilla', 'version'])->name('plantillas.restaurar');
        });
        Route::middleware('permiso:mantenedores.gestionar')->group(function () {
            Route::get('/usuarios', [UsuariosController::class, 'index'])->name('usuarios');
            Route::get('/usuarios/nuevo', [UsuariosController::class, 'crear'])->name('usuarios.crear');
            Route::post('/usuarios', [UsuariosController::class, 'guardar'])->name('usuarios.guardar');
            Route::get('/usuarios/{usuario}', [UsuariosController::class, 'editar'])->whereNumber('usuario')->name('usuarios.editar');
            Route::post('/usuarios/{usuario}', [UsuariosController::class, 'guardar'])->whereNumber('usuario')->name('usuarios.actualizar');
            Route::post('/usuarios/{usuario}/activo', [UsuariosController::class, 'alternar'])->whereNumber('usuario')->name('usuarios.alternar');
        });
        Route::middleware('permiso:menu.gestionar')->group(function () {
            Route::get('/menu', [MenuController::class, 'index'])->name('menu');
            Route::post('/menu/sistema/{clave}', [MenuController::class, 'sistema'])->name('menu.sistema');
            Route::post('/menu/sistema/{clave}/restablecer', [MenuController::class, 'restablecer'])->name('menu.restablecer');
            Route::post('/menu/propio/{item?}', [MenuController::class, 'propio'])->name('menu.propio');
            Route::post('/menu/propio/{item}/eliminar', [MenuController::class, 'eliminar'])->name('menu.eliminar');
        });
        Route::middleware('permiso:api.gestionar')->group(function () {
            Route::get('/api', [ApiConfigController::class, 'index'])->name('api');
            Route::get('/api/{servicio}', [ApiConfigController::class, 'editar'])->name('api.editar');
            Route::post('/api/{servicio}', [ApiConfigController::class, 'actualizar'])->name('api.actualizar');
            Route::post('/api-firma-prueba', [ApiConfigController::class, 'firmaPrueba'])->middleware('throttle:5,1')->name('api.firma_prueba');
            Route::post('/api-directorio-sincronizar', [ApiConfigController::class, 'directorioSincronizar'])->middleware('throttle:3,1')->name('api.directorio_sincronizar');
            Route::post('/api-directorio-prueba', [ApiConfigController::class, 'directorioPrueba'])->middleware('throttle:10,1')->name('api.directorio_prueba');
            Route::post('/api-correo-prueba', [ApiConfigController::class, 'correoPrueba'])->middleware('throttle:5,1')->name('api.correo_prueba');
            Route::post('/api/{servicio}/probar', [ApiConfigController::class, 'probar'])->name('api.probar');
            Route::post('/api/{servicio}/env', [ApiConfigController::class, 'volverEnv'])->name('api.env');
        });
        Route::middleware('permiso:repositorio.gestionar')->group(function () {
            Route::get('/repositorio', [RepositorioController::class, 'index'])->name('repositorio');
            Route::post('/repositorio', [RepositorioController::class, 'guardar'])->name('repositorio.guardar');
            Route::post('/repositorio/probar', [RepositorioController::class, 'probar'])->name('repositorio.probar');
            Route::post('/repositorio/verificar', [RepositorioController::class, 'verificar'])->name('repositorio.verificar');
            Route::post('/repositorio/migrar', [RepositorioController::class, 'migrar'])->name('repositorio.migrar');
        });
        Route::middleware('permiso:firmas.gestionar')->group(function () {
            Route::get('/firmas', [FirmasController::class, 'index'])->name('firmas');
            Route::get('/firmas/prueba', [FirmasController::class, 'prueba'])->name('firmas.prueba');
            Route::post('/firmas/estilos/{estilo?}', [FirmasController::class, 'guardarEstilo'])->name('firmas.estilo.guardar');
            Route::post('/firmas/estilos/{estilo}/defecto', [FirmasController::class, 'estiloDefecto'])->name('firmas.estilo.defecto');
            Route::post('/firmas/estilos/{estilo}/activo', [FirmasController::class, 'estiloAlternar'])->name('firmas.estilo.activo');
            Route::post('/firmas/posiciones/{posicion?}', [FirmasController::class, 'guardarPosicion'])->name('firmas.posicion.guardar');
            Route::post('/firmas/posiciones/{posicion}/activo', [FirmasController::class, 'posicionAlternar'])->name('firmas.posicion.activo');
        });
        Route::middleware('permiso:folios.gestionar')->group(function () {
            Route::get('/folios', [FoliosController::class, 'index'])->name('folios');
            Route::get('/folios/{tipo}', [FoliosController::class, 'editar'])->name('folios.editar');
            Route::post('/folios/{tipo}', [FoliosController::class, 'actualizar'])->name('folios.actualizar');
            Route::post('/folios/{tipo}/ajustar', [FoliosController::class, 'ajustar'])->name('folios.ajustar');
        });
    });

    Route::middleware('permiso:mantenedores.gestionar,codificador.gestionar')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/codificador', [InteropController::class, 'codificador'])->name('codificador');
        Route::post('/codificador/importar', [InteropController::class, 'importar'])->name('codificador.importar');
        Route::post('/codificador/sincronizar', [InteropController::class, 'sincronizar'])->name('codificador.sincronizar');
        Route::get('/{entidad}', [MantenedorController::class, 'index'])->name('index');
        Route::get('/{entidad}/nuevo', [MantenedorController::class, 'crear'])->name('crear');
        Route::post('/{entidad}', [MantenedorController::class, 'guardar'])->name('guardar');
        Route::get('/{entidad}/{id}', [MantenedorController::class, 'editar'])->name('editar');
        Route::post('/{entidad}/{id}', [MantenedorController::class, 'actualizar'])->name('actualizar');
        Route::post('/{entidad}/{id}/activo', [MantenedorController::class, 'alternar'])->name('alternar');
    });
});
