<?php

namespace Database\Seeders;

use App\Support\Ambiente;
use App\Support\Permisos;
use App\Support\PlantillaEjemplos;
use App\Support\PlantillaEstructura;
use App\Support\Run;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ===== SOLO DESARROLLO =====  Datos de demostración. No cargar en producción.
 * Los códigos CPAT son EJEMPLOS con el formato oficial; deben reemplazarse por los reales del catálogo CPAT.
 */
class DemoSeeder extends Seeder
{
    public const NOW = '2026-01-01 00:00:00';

    public function run(): void
    {
        if (app()->isProduction() && ! Ambiente::esPruebas()) {
            throw new \RuntimeException('DemoSeeder (datos ficticios) solo puede ejecutarse en local o con SGD_AMBIENTE=pruebas.');
        }
        $t = ['created_at' => self::NOW, 'updated_at' => self::NOW];
        DB::table('unidades')->insert([
            ['id' => 1, 'codigo' => 'ALC', 'nombre' => 'Alcaldía', 'activo' => 1] + $t,
            ['id' => 2, 'codigo' => 'SECMUN', 'nombre' => 'Secretaría Municipal', 'activo' => 1] + $t,
            ['id' => 3, 'codigo' => 'DOM', 'nombre' => 'Dirección de Obras Municipales', 'activo' => 1] + $t,
            ['id' => 4, 'codigo' => 'DIDECO', 'nombre' => 'Dirección de Desarrollo Comunitario', 'activo' => 1] + $t,
            ['id' => 5, 'codigo' => 'ARCH', 'nombre' => 'Archivo Municipal', 'activo' => 1] + $t,
            ['id' => 6, 'codigo' => 'TRANSP', 'nombre' => 'Unidad de Transparencia', 'activo' => 1] + $t,
            ['id' => 7, 'codigo' => 'OPARTES', 'nombre' => 'Oficina de Partes', 'activo' => 1] + $t,
            ['id' => 8, 'codigo' => 'DAF', 'nombre' => 'Dirección de Administración y Finanzas', 'activo' => 1] + $t,
        ]);

        $u = fn (int $id, int $cuerpo, string $nom, string $ape, string $rol, ?int $unidad, bool $firma, string $cargo) => [
            'id' => $id, 'run' => $cuerpo.'-'.Run::dv((string) $cuerpo), 'nombres' => $nom, 'apellidos' => $ape,
            'correo' => strtolower($nom).'.'.strtolower(explode(' ', $ape)[0]).'@demo.santodomingo.invalid', 'cargo' => $cargo, 'rol' => $rol,
            'unidad_id' => $unidad, 'puede_firmar' => (int) $firma, 'activo' => 1,
        ] + $t;
        // Roles personalizados de demostración para la gestión de trámites (base funcionario + permisos opcionales)
        $ahora = now();
        foreach ([
            ['dir_tramites', 'Director(a) de trámites', 'Crea, edita, publica y deshabilita los trámites de su dirección.', ['tramites.gestionar', 'tramites.publicar']],
            ['edit_tramites', 'Redactor(a) de trámites', 'Crea y edita borradores de trámites de su dirección; no puede publicarlos.', ['tramites.gestionar']],
            ['arch_gestion', 'Encargado(a) de archivo', 'Registra microformas, carga sus archivos e índice y gestiona la disposición de originales.', ['archivo.ver', 'archivo.gestionar'], 'funcionario'],
            ['arch_cert', 'Ministro(a) de fe del archivo', 'Certifica microformas (acta) y autoriza la disposición de originales.', ['archivo.ver', 'archivo.certificar', 'archivo.disposicion'], 'secretario'],
        ] as $i => $fila) {
            [$clave, $nombre, $desc, $extra, $base] = array_pad($fila, 5, 'funcionario');
            $rid = 12 + $i; // los 11 roles del sistema ocupan 1-11; id explícito para que 02_seed_demo.sql sea generable
            DB::table('roles')->insert(['id' => $rid, 'clave' => $clave, 'nombre' => $nombre, 'descripcion' => $desc, 'rol_base' => $base, 'sistema' => 0, 'activo' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
            foreach (array_unique([...Permisos::deBase($base), ...$extra]) as $perm) {
                DB::table('rol_permisos')->insert(['rol_id' => $rid, 'permiso' => $perm]);
            }
        }
        DB::table('usuarios')->insert([
            $u(1, 10000001, 'Ana', 'Administradora Demo', 'admin', null, false, 'Administradora del sistema'),
            $u(2, 10000002, 'Carlos', 'Alcalde Demo', 'alcalde', 1, true, 'Alcalde'),
            $u(3, 10000003, 'Sofía', 'Secretaria Demo', 'secretario', 2, true, 'Secretaria Municipal'),
            $u(4, 10000004, 'Diego', 'Funcionario DOM', 'funcionario', 3, false, 'Funcionario DOM'),
            $u(5, 10000005, 'Paula', 'Revisora DOM', 'funcionario', 3, false, 'Revisora DOM'),
            $u(6, 10000006, 'Marcos', 'Funcionario DIDECO', 'funcionario', 4, false, 'Funcionario DIDECO'),
            $u(7, 10000007, 'Aurora', 'Auditora Demo', 'auditor', null, false, 'Auditoría interna'),
            $u(8, 10000008, 'Daniel', 'Director DOM', 'funcionario', 3, true, 'Director de Obras'),
            $u(9, 10000009, 'Elena', 'Directora DIDECO', 'dir_tramites', 4, false, 'Directora de Desarrollo Comunitario'),
            $u(10, 10000010, 'Mario', 'Redactor DOM', 'edit_tramites', 3, false, 'Redactor de trámites DOM'),
            $u(11, 10000011, 'Ximena', 'Archivista Municipal', 'arch_gestion', 5, false, 'Encargada del Archivo Municipal'),
            $u(12, 10000012, 'Tomás', 'Ministro de Fe', 'arch_cert', 2, false, 'Ministro de fe (Secretaría Municipal)'),
            $u(13, 10000013, 'Alicia', 'Autoridad Demo', 'autoridad', null, false, 'Autoridad municipal'),
            $u(14, 10000014, 'Omar', 'OIRS Demo', 'oirs', null, false, 'Encargado OIRS'),
            $u(15, 10000015, 'Teresa', 'Transparencia Demo', 'transparencia', 6, false, 'Encargada de Transparencia'),
            $u(16, 10000016, 'Patricia', 'Oficina de Partes Demo', 'oficina_partes', 7, false, 'Encargada de Oficina de Partes'),
            $u(17, 10000017, 'Bernardo', 'Pagos Demo', 'contabilidad', 8, false, 'Encargado de pagos'),
            $u(18, 10000018, 'Tamara', 'Tesorera Demo', 'tesorero', 8, true, 'Tesorera Municipal'),
        ]);

        DB::table('tramites')->insert([
            ['id' => 1, 'codigo_interno' => 'DOM-PE', 'nombre' => 'Permiso de edificación', 'descripcion' => 'Solicitud de permiso de edificación de obra nueva.', 'codigo_cpat' => 'PA-UNI00002-00001', 'plazo_dias' => 30, 'activo' => 1] + $t,
            ['id' => 2, 'codigo_interno' => 'DOM-CN', 'nombre' => 'Certificado de número', 'descripcion' => 'Certificado de numeración domiciliaria.', 'codigo_cpat' => 'PA-UNI00002-00002', 'plazo_dias' => 5, 'activo' => 1] + $t,
            ['id' => 3, 'codigo_interno' => 'DIDECO-BS', 'nombre' => 'Solicitud de beneficio social', 'descripcion' => 'Postulación a beneficios sociales municipales.', 'codigo_cpat' => 'PA-UNI00003-00001', 'plazo_dias' => 15, 'activo' => 1] + $t,
            ['id' => 4, 'codigo_interno' => 'ALC-AU', 'nombre' => 'Solicitud de audiencia con el alcalde', 'descripcion' => 'Solicitud de audiencia (la atienden Alcaldía y Secretaría).', 'codigo_cpat' => null, 'plazo_dias' => 20, 'activo' => 1] + $t,
        ]);
        DB::table('instituciones')->insert([
            ['codigo' => 'DEMO-MUN-SD', 'nombre' => 'I. Municipalidad de Santo Domingo (código de EJEMPLO)', 'tipo' => 'Municipalidad', 'codigo_padre' => null, 'activo' => 1, 'origen' => 'manual', 'sincronizado_en' => null] + $t,
            ['codigo' => 'DEMO-SEGPRES', 'nombre' => 'Ministerio Secretaría General de la Presidencia (EJEMPLO)', 'tipo' => 'Ministerio', 'codigo_padre' => null, 'activo' => 1, 'origen' => 'manual', 'sincronizado_en' => null] + $t,
            ['codigo' => 'DEMO-GORE-VAL', 'nombre' => 'Gobierno Regional de Valparaíso (EJEMPLO)', 'tipo' => 'Gobierno Regional', 'codigo_padre' => null, 'activo' => 1, 'origen' => 'manual', 'sincronizado_en' => null] + $t,
        ]);
        DB::table('procesos_codificador')->insert([
            ['codigo' => 'DEMO-PROC-EDIF', 'nombre' => 'Permisos de edificación (EJEMPLO)', 'institucion_codigo' => 'DEMO-MUN-SD', 'activo' => 1, 'origen' => 'manual', 'sincronizado_en' => null] + $t,
            ['codigo' => 'DEMO-PROC-CERT', 'nombre' => 'Certificados municipales (EJEMPLO)', 'institucion_codigo' => 'DEMO-MUN-SD', 'activo' => 1, 'origen' => 'manual', 'sincronizado_en' => null] + $t,
        ]);
        DB::table('tramites')->insert([
            ['id' => 5, 'codigo_interno' => 'TRANSP-SAI', 'nombre' => 'Solicitud de acceso a la información pública (Ley 20.285)', 'descripcion' => 'Solicite información que obre en poder de la municipalidad. Se responde en 20 días hábiles (prorrogables una vez).', 'codigo_cpat' => null, 'plazo_dias' => 20, 'activo' => 1, 'es_transparencia' => 1, 'plazo_habiles' => 1] + $t,
        ]);
        DB::table('tramites')->where('id', 1)->update(['proceso_codigo' => 'DEMO-PROC-EDIF']);
        // los trámites de demostración nacen publicados; la dirección propietaria es la primera responsable
        foreach ([1 => 3, 2 => 3, 3 => 4, 4 => 1, 5 => 6] as $tid => $uid) {
            DB::table('tramites')->where('id', $tid)->update(['estado' => 'publicado', 'activo' => 1, 'unidad_propietaria_id' => $uid, 'publicado_en' => now(), 'version' => 0]);
        }
        DB::table('pisee_servicios')->insert([
            ['codigo' => 'DEMO-CONSULTA', 'nombre' => 'Consulta de ejemplo (SIN contrato real)', 'institucion_codigo' => 'DEMO-SEGPRES', 'ruta' => '/demo/v1/consulta', 'metodo' => 'GET', 'param_run' => 'run', 'descripcion' => 'Servicio ficticio solo para desarrollo.', 'activo' => 1] + $t,
        ]);
        DB::table('tramite_unidad')->insert([
            ['tramite_id' => 1, 'unidad_id' => 3], ['tramite_id' => 2, 'unidad_id' => 3], ['tramite_id' => 3, 'unidad_id' => 4],
            ['tramite_id' => 4, 'unidad_id' => 1], ['tramite_id' => 4, 'unidad_id' => 2], ['tramite_id' => 5, 'unidad_id' => 6],
        ]);
        DB::table('tramite_anexos')->insert([
            ['id' => 1, 'tramite_id' => 1, 'nombre' => 'Planos de arquitectura', 'obligatorio' => 1, 'activo' => 1],
            ['id' => 2, 'tramite_id' => 1, 'nombre' => 'Certificado de dominio vigente', 'obligatorio' => 1, 'activo' => 1],
            ['id' => 3, 'tramite_id' => 1, 'nombre' => 'Fotografías del terreno', 'obligatorio' => 0, 'activo' => 1],
            ['id' => 4, 'tramite_id' => 2, 'nombre' => 'Copia de cédula de identidad', 'obligatorio' => 1, 'activo' => 1],
            ['id' => 5, 'tramite_id' => 3, 'nombre' => 'Ficha de protección social', 'obligatorio' => 0, 'activo' => 1],
        ]);

        $tipos = [
            [1, 'DEC_ALC', 'Decreto alcaldicio', 'Decreto', 1], [2, 'DEC_SIAPER', 'Decreto SIAPER', 'Decreto SIAPER', 0], [3, 'OFICIO', 'Oficio', 'Oficio', 0],
            [4, 'ORDENANZA', 'Ordenanza', 'Ordenanza', 0], [5, 'REGLAMENTO', 'Reglamento', 'Reglamento', 0], [6, 'INSTRUCTIVO', 'Instructivo', 'Instructivo', 0],
            [7, 'DECRETO_PAGO', 'Decreto de pago', 'Decreto de pago', 1], [8, 'EGRESO', 'Egreso', 'Egreso', 0],
        ];
        foreach ($tipos as [$id, $cod, $nom, $pre, $alc]) {
            DB::table('tipos_documento')->insert(['id' => $id, 'codigo' => $cod, 'nombre' => $nom, 'prefijo' => $pre, 'firma_alcalde' => $alc, 'firmas_agil' => 1, 'firmas_clasico' => 2, 'activo' => 1, 'permite_firma_simple' => (int) ($cod === 'EGRESO')] + $t);
            DB::table('plantillas')->insert([
                'id' => $id, 'tipo_documento_id' => $id, 'nombre' => $nom.' (base)', 'activo' => 1,
                'contenido_html' => '<h2>'.$nom.'</h2><p>Santo Domingo, {{fecha}}.</p><p>VISTOS: la solicitud folio {{folio}} de {{solicitante_nombre}}, RUN {{solicitante_run}}, expediente {{expediente}} ({{tramite}}).</p><p>CONSIDERANDO: [COMPLETAR]</p><p>DECRETO / RESUELVO: [COMPLETAR]</p><p>Anótese, comuníquese y archívese.</p>',
            ] + $t);
        }
        $codigoATipo = ['DEC_ALC' => 1, 'OFICIO' => 3, 'ORDENANZA' => 4, 'REGLAMENTO' => 5, 'INSTRUCTIVO' => 6, 'DECRETO_PAGO' => 7];
        foreach (PlantillaEjemplos::definiciones() as $i => $ej) { // plantillas de ejemplo por secciones (decretos, oficios y demás)
            $htmlEj = PlantillaEstructura::componer($ej['estructura']);
            DB::table('plantillas')->insert(['id' => 9 + $i, 'tipo_documento_id' => $codigoATipo[$ej['tipo_codigo']], 'nombre' => $ej['nombre'], 'descripcion' => $ej['descripcion'], 'activo' => 1,
                'contenido_html' => $htmlEj, 'estructura' => json_encode($ej['estructura'], JSON_UNESCAPED_UNICODE)] + $t);
        }
        foreach (DB::table('plantillas')->get() as $pl) { // versión 1 de cada plantilla (historial)
            DB::table('plantilla_versiones')->insert(['plantilla_id' => $pl->id, 'version' => 1, 'nombre' => $pl->nombre, 'contenido_html' => $pl->contenido_html, 'estructura' => $pl->estructura ?? null,
                'sha256' => hash('sha256', (string) $pl->contenido_html), 'created_at' => '2026-01-01 00:00:00']);
        }
        DB::table('pago_firmantes')->insert([ // configuración de demostración del decreto de pago y del egreso
            ['id' => 1, 'documento' => 'decreto_pago', 'usuario_id' => 3, 'cargo' => 'Secretaria Municipal', 'funcion' => 'visacion', 'firma_tipo' => 'avanzada', 'orden' => 1, 'activo' => 1] + $t,
            ['id' => 2, 'documento' => 'decreto_pago', 'usuario_id' => 2, 'cargo' => 'Alcalde', 'funcion' => 'firma', 'firma_tipo' => 'avanzada', 'orden' => 2, 'activo' => 1] + $t,
            ['id' => 3, 'documento' => 'egreso', 'usuario_id' => 18, 'cargo' => 'Tesorera Municipal', 'funcion' => 'firma', 'firma_tipo' => 'ambas', 'orden' => 1, 'activo' => 1] + $t,
        ]);
        DB::table('delegaciones_firma')->insert([
            'id' => 1, 'tipo_documento_id' => 1, 'delegado_id' => 8, 'decreto_referencia' => 'Decreto Alcaldicio N° 123/2026 (DEMO)',
            'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-12-31', 'creado_por' => 2, 'activo' => 1,
        ] + $t);
    }
}
