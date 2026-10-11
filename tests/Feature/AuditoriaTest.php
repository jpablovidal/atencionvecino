<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\AuditTriggers;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadena_hmac_se_verifica_y_enlaza_registros(): void
    {
        $a = app(AuditoriaService::class);
        $r1 = $a->registrar('prueba.uno', 'x', 1, ['k' => 'v']);
        $r2 = $a->registrar('prueba.dos', 'x', 2, [], true);
        $this->assertSame(AuditoriaService::GENESIS, $r1->prev_hmac);
        $this->assertSame($r1->hmac, $r2->prev_hmac);
        $this->assertSame(['ok' => true, 'total' => 2, 'roto_en' => null], $a->verificar());
    }

    public function test_alteracion_o_borrado_se_detectan(): void
    {
        $a = app(AuditoriaService::class);
        foreach (range(1, 4) as $i) {
            $a->registrar('evento.'.$i, 'x', $i);
        }
        // un atacante con acceso directo a la BD (se quitan los triggers para simularlo)
        foreach (AuditTriggers::drop() as $sql) {
            DB::unprepared($sql);
        }
        DB::table('audit_log')->where('id', 2)->update(['accion' => 'evento.falsificado']);
        $this->assertSame(2, $a->verificar()['roto_en']);
        DB::table('audit_log')->where('id', 2)->update(['accion' => 'evento.2']);
        $this->assertTrue($a->verificar()['ok']);
        DB::table('audit_log')->where('id', 3)->delete(); // borrado de un eslabón
        $res = $a->verificar();
        $this->assertFalse($res['ok']);
        $this->assertSame(4, $res['roto_en']);
    }

    public function test_inmutable_por_modelo_y_por_trigger(): void
    {
        $r = app(AuditoriaService::class)->registrar('x.y');
        try {
            $r->update(['accion' => 'otra']);
            $this->fail('El modelo debe impedir UPDATE');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        try {
            $r->delete();
            $this->fail('El modelo debe impedir DELETE');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(QueryException::class); // trigger de la BD
        DB::table('audit_log')->where('id', $r->id)->update(['accion' => 'z']);
    }

    public function test_trigger_bloquea_delete(): void
    {
        $r = app(AuditoriaService::class)->registrar('x.y');
        $this->expectException(QueryException::class);
        DB::table('audit_log')->where('id', $r->id)->delete();
    }

    public function test_no_registra_secretos_en_el_detalle(): void
    {
        $r = app(AuditoriaService::class)->registrar('x', null, null, ['otp' => '123456', 'client_secret' => 'abc', 'anidado' => ['token' => 'zzz'], 'ok' => 'visible']);
        $this->assertStringNotContainsString('123456', $r->detalle);
        $this->assertStringNotContainsString('abc', $r->detalle);
        $this->assertStringNotContainsString('zzz', $r->detalle);
        $this->assertStringContainsString('visible', $r->detalle);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_panel_de_seguridad_verifica_la_cadena(): void
    {
        app(AuditoriaService::class)->registrar('a');
        $admin = Usuario::query()->create(['run' => '10000001-1', 'nombres' => 'A', 'apellidos' => 'B', 'correo' => 'a@b.cl', 'rol' => 'admin', 'activo' => true]);
        $this->actingAs($admin)->withSession(['login_at' => time(), 'last_seen' => time()])->post('/app/seguridad/verificar')->assertRedirect('/app/seguridad')->assertSessionHas('verificacion');
    }
}
