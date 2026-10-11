<?php

namespace Database\Seeders;

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
        $t = ['created_at' => self::NOW, 'updated_at' => self::NOW];
        DB::table('unidades')->insert([
            ['id' => 1, 'codigo' => 'ALC', 'nombre' => 'Alcaldía', 'activo' => 1] + $t,
            ['id' => 2, 'codigo' => 'SECMUN', 'nombre' => 'Secretaría Municipal', 'activo' => 1] + $t,
            ['id' => 3, 'codigo' => 'DOM', 'nombre' => 'Dirección de Obras Municipales', 'activo' => 1] + $t,
            ['id' => 4, 'codigo' => 'DIDECO', 'nombre' => 'Dirección de Desarrollo Comunitario', 'activo' => 1] + $t,
        ]);

        $u = fn (int $id, int $cuerpo, string $nom, string $ape, string $rol, ?int $unidad, bool $firma, string $cargo) => [
            'id' => $id, 'run' => $cuerpo.'-'.Run::dv((string) $cuerpo), 'nombres' => $nom, 'apellidos' => $ape,
            'correo' => strtolower($nom).'.'.strtolower(explode(' ', $ape)[0]).'@demo.santodomingo.invalid', 'cargo' => $cargo, 'rol' => $rol,
            'unidad_id' => $unidad, 'puede_firmar' => (int) $firma, 'activo' => 1,
        ] + $t;
        DB::table('usuarios')->insert([
            $u(1, 10000001, 'Ana', 'Administradora Demo', 'admin', null, false, 'Administradora del sistema'),
            $u(2, 10000002, 'Carlos', 'Alcalde Demo', 'alcalde', 1, true, 'Alcalde'),
            $u(3, 10000003, 'Sofía', 'Secretaria Demo', 'secretario', 2, true, 'Secretaria Municipal'),
            $u(4, 10000004, 'Diego', 'Funcionario DOM', 'funcionario', 3, false, 'Funcionario DOM'),
            $u(5, 10000005, 'Paula', 'Revisora DOM', 'funcionario', 3, false, 'Revisora DOM'),
            $u(6, 10000006, 'Marcos', 'Funcionario DIDECO', 'funcionario', 4, false, 'Funcionario DIDECO'),
            $u(7, 10000007, 'Aurora', 'Auditora Demo', 'auditor', null, false, 'Auditoría interna'),
            $u(8, 10000008, 'Daniel', 'Director DOM', 'funcionario', 3, true, 'Director de Obras'),
        ]);

        DB::table('tramites')->insert([
            ['id' => 1, 'codigo_interno' => 'DOM-PE', 'nombre' => 'Permiso de edificación', 'descripcion' => 'Solicitud de permiso de edificación de obra nueva.', 'codigo_cpat' => 'PA-UNI00002-00001', 'plazo_dias' => 30, 'activo' => 1] + $t,
            ['id' => 2, 'codigo_interno' => 'DOM-CN', 'nombre' => 'Certificado de número', 'descripcion' => 'Certificado de numeración domiciliaria.', 'codigo_cpat' => 'PA-UNI00002-00002', 'plazo_dias' => 5, 'activo' => 1] + $t,
            ['id' => 3, 'codigo_interno' => 'DIDECO-BS', 'nombre' => 'Solicitud de beneficio social', 'descripcion' => 'Postulación a beneficios sociales municipales.', 'codigo_cpat' => 'PA-UNI00003-00001', 'plazo_dias' => 15, 'activo' => 1] + $t,
            ['id' => 4, 'codigo_interno' => 'ALC-AU', 'nombre' => 'Solicitud de audiencia con el alcalde', 'descripcion' => 'Solicitud de audiencia (la atienden Alcaldía y Secretaría).', 'codigo_cpat' => null, 'plazo_dias' => 20, 'activo' => 1] + $t,
        ]);
        DB::table('tramite_unidad')->insert([
            ['tramite_id' => 1, 'unidad_id' => 3], ['tramite_id' => 2, 'unidad_id' => 3], ['tramite_id' => 3, 'unidad_id' => 4],
            ['tramite_id' => 4, 'unidad_id' => 1], ['tramite_id' => 4, 'unidad_id' => 2],
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
        ];
        foreach ($tipos as [$id, $cod, $nom, $pre, $alc]) {
            DB::table('tipos_documento')->insert(['id' => $id, 'codigo' => $cod, 'nombre' => $nom, 'prefijo' => $pre, 'firma_alcalde' => $alc, 'firmas_agil' => 1, 'firmas_clasico' => 2, 'activo' => 1] + $t);
            DB::table('plantillas')->insert([
                'id' => $id, 'tipo_documento_id' => $id, 'nombre' => $nom.' (base)', 'activo' => 1,
                'contenido_html' => '<h2>'.$nom.'</h2><p>Santo Domingo, {{fecha}}.</p><p>VISTOS: la solicitud folio {{folio}} de {{solicitante_nombre}}, RUN {{solicitante_run}}, expediente {{expediente}} ({{tramite}}).</p><p>CONSIDERANDO: [COMPLETAR]</p><p>DECRETO / RESUELVO: [COMPLETAR]</p><p>Anótese, comuníquese y archívese.</p>',
            ] + $t);
        }
        DB::table('delegaciones_firma')->insert([
            'id' => 1, 'tipo_documento_id' => 1, 'delegado_id' => 8, 'decreto_referencia' => 'Decreto Alcaldicio N° 123/2026 (DEMO)',
            'vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-12-31', 'creado_por' => 2, 'activo' => 1,
        ] + $t);
    }
}
