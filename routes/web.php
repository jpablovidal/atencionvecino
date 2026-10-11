<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CiudadanoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\MantenedorController;
use App\Http\Controllers\PublicoController;
use App\Http\Controllers\SeguridadController;
use Illuminate\Support\Facades\Route;

// ---- Público
Route::get('/', [PublicoController::class, 'inicio'])->name('inicio');
Route::middleware('throttle:publico')->group(function () {
    Route::get('/publico', [PublicoController::class, 'listado'])->name('publico.listado');
    Route::get('/validar', [PublicoController::class, 'formValidar'])->name('validar.form');
    Route::post('/validar', [PublicoController::class, 'buscarValidar'])->name('validar.buscar');
    Route::get('/validar/{codigo}', [PublicoController::class, 'validar'])->name('validar')->where('codigo', 'SDD-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}');
    Route::get('/validar/{codigo}/copia', [PublicoController::class, 'copia'])->name('validar.copia')->where('codigo', 'SDD-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}');
    Route::get('/validar-comprobante/{folio}/{codigo}', [PublicoController::class, 'validarComprobante'])->name('validar.comprobante');
});

// ---- Autenticación (solo ClaveÚnica)
Route::get('/ingreso', [AuthController::class, 'ingreso'])->name('ingreso');
Route::get('/ingreso/{via}', [AuthController::class, 'redirigir'])->where('via', 'ciudadano|funcionario')->middleware('throttle:login')->name('ingreso.via');
Route::get('/claveunica/callback', [AuthController::class, 'callback'])->middleware('throttle:login')->name('claveunica.callback');
Route::post('/salir', [AuthController::class, 'salir'])->name('salir');
if (config('sgd.claveunica.mode') === 'mock' && ! app()->isProduction()) {
    Route::get('/dev/login', [AuthController::class, 'devForm'])->name('dev.login');
    Route::post('/dev/login', [AuthController::class, 'devLogin'])->middleware('throttle:login');
}

// ---- Ciudadano
Route::prefix('ciudadano')->middleware(['ciudadano', 'nostore'])->name('ciudadano.')->group(function () {
    Route::get('/', [CiudadanoController::class, 'index'])->name('index');
    Route::get('/nueva/{tramite}', [CiudadanoController::class, 'nueva'])->name('nueva');
    Route::post('/solicitudes', [CiudadanoController::class, 'store'])->middleware('throttle:envio')->name('store');
    Route::get('/solicitudes/{folio}', [CiudadanoController::class, 'solicitud'])->name('solicitud');
    Route::post('/solicitudes/{folio}/responder', [CiudadanoController::class, 'responder'])->middleware('throttle:envio')->name('responder');
    Route::get('/solicitudes/{folio}/comprobante', [CiudadanoController::class, 'comprobante'])->name('comprobante');
    Route::get('/solicitudes/{folio}/documentos/{documento}', [CiudadanoController::class, 'documento'])->name('documento');
});

// ---- Funcionarios
Route::prefix('app')->middleware(['funcionario', 'nostore'])->name('app.')->group(function () {
    Route::get('/', [FuncionarioController::class, 'bandeja'])->name('bandeja');
    Route::get('/vigentes', [FuncionarioController::class, 'vigentes'])->name('vigentes');
    Route::get('/solicitudes/{folio}', [FuncionarioController::class, 'solicitud'])->name('solicitud');
    Route::post('/solicitudes/{folio}/asignar', [FuncionarioController::class, 'asignar'])->name('asignar');
    Route::get('/solicitudes/{folio}/archivos/{archivo}', [FuncionarioController::class, 'archivoSolicitud'])->name('solicitud.archivo');

    Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show'])->name('expediente');
    Route::post('/expedientes/{expediente}/metadatos', [ExpedienteController::class, 'metadatos'])->name('expediente.metadatos');
    Route::post('/expedientes/{expediente}/comentarios', [ExpedienteController::class, 'comentar'])->name('expediente.comentar');
    Route::post('/expedientes/{expediente}/devolver', [ExpedienteController::class, 'devolver'])->name('expediente.devolver');
    Route::post('/expedientes/{expediente}/derivar', [ExpedienteController::class, 'derivar'])->name('expediente.derivar');
    Route::post('/expedientes/{expediente}/archivos', [ExpedienteController::class, 'subir'])->name('expediente.subir');
    Route::get('/expedientes/{expediente}/archivos/{archivo}', [ExpedienteController::class, 'archivo'])->name('expediente.archivo');
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

    Route::middleware('rol:admin,auditor')->group(function () {
        Route::get('/seguridad', [SeguridadController::class, 'panel'])->name('seguridad');
        Route::post('/seguridad/verificar', [SeguridadController::class, 'verificar'])->name('seguridad.verificar');
        Route::post('/seguridad/incidentes', [SeguridadController::class, 'incidente'])->name('seguridad.incidente');
        Route::post('/seguridad/incidentes/{incidente}', [SeguridadController::class, 'actualizarIncidente'])->name('seguridad.incidente.actualizar');
    });
    Route::get('/diagnostico', [SeguridadController::class, 'diagnostico'])->middleware('rol:admin')->name('diagnostico');

    Route::get('/delegaciones', [MantenedorController::class, 'delegaciones'])->middleware('rol:alcalde,admin,auditor')->name('delegaciones');
    Route::post('/delegaciones', [MantenedorController::class, 'crearDelegacion'])->middleware('rol:alcalde')->name('delegaciones.crear');
    Route::post('/delegaciones/{delegacion}/revocar', [MantenedorController::class, 'revocarDelegacion'])->middleware('rol:alcalde')->name('delegaciones.revocar');

    Route::middleware('rol:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/{entidad}', [MantenedorController::class, 'index'])->name('index');
        Route::get('/{entidad}/nuevo', [MantenedorController::class, 'crear'])->name('crear');
        Route::post('/{entidad}', [MantenedorController::class, 'guardar'])->name('guardar');
        Route::get('/{entidad}/{id}', [MantenedorController::class, 'editar'])->name('editar');
        Route::post('/{entidad}/{id}', [MantenedorController::class, 'actualizar'])->name('actualizar');
        Route::post('/{entidad}/{id}/activo', [MantenedorController::class, 'alternar'])->name('alternar');
    });
});
