<?php

namespace Tests\Feature;

use App\Models\ListaOpciones;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\TramiteCampo;
use App\Models\Usuario;
use App\Services\ExpedienteService;
use App\Services\TramiteCamposService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Escenario;
use Tests\TestCase;

/** Constructor de formularios de los trámites: campos, listados reutilizables, cálculos y respuestas de la ciudadanía. */
class TramiteCamposTest extends TestCase
{
    use Escenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrar();
        Carbon::setTestNow('2026-10-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function director(): Usuario
    {
        return Usuario::query()->where('rol', 'dir_tramites')->firstOrFail(); // DIDECO: gestiona y publica trámites y opera expedientes
    }

    /** @return array<string,mixed> */
    private function base(array $campos): array
    {
        return ['nombre' => 'Permiso de ocupación de bien nacional', 'descripcion' => 'Permite ocupar temporalmente un bien nacional de uso público con un puesto o instalación.', 'plazo_dias' => 10, 'campos_enviados' => 1, 'campos' => $campos];
    }

    /** @return list<array<string,mixed>> */
    private function camposEjemplo(): array
    {
        return [
            ['etiqueta' => 'Superficie a ocupar', 'tipo' => 'numero', 'clave' => 'superficie', 'obligatorio' => 1, 'min' => '1', 'max' => '500', 'decimales' => '2', 'sufijo' => 'm²'],
            ['etiqueta' => 'Zona', 'tipo' => 'lista', 'clave' => 'zona', 'obligatorio' => 1, 'opciones' => "1000;Centro\n600;Costanera\n300;Rural"],
            ['etiqueta' => 'Fecha de inicio', 'tipo' => 'fecha', 'clave' => 'inicio', 'obligatorio' => 1, 'fecha_min' => 'hoy'],
            ['etiqueta' => 'Fecha de término', 'tipo' => 'fecha', 'clave' => 'termino', 'obligatorio' => 1, 'fecha_min' => 'hoy'],
            ['etiqueta' => 'Es sin fines de lucro', 'tipo' => 'si_no', 'clave' => 'sin_lucro', 'obligatorio' => 1],
            ['etiqueta' => 'Días de ocupación', 'tipo' => 'calculo', 'clave' => 'dias_total', 'formula' => 'dias(inicio; termino) + 1', 'decimales' => '0'],
            ['etiqueta' => 'Derecho a pagar', 'tipo' => 'calculo', 'clave' => 'derecho', 'formula' => 'si(sin_lucro == 1; 0; redondear(superficie * zona * dias_total / 30; 0))', 'decimales' => '0', 'prefijo' => '$'],
            ['etiqueta' => 'Observaciones', 'tipo' => 'parrafo', 'clave' => 'obs', 'maxlen' => '100'],
            ['etiqueta' => 'Servicios que necesita', 'tipo' => 'multiple', 'clave' => 'servicios', 'opciones' => "Agua\nLuz\nBaños", 'max_elegidas' => '2'],
        ];
    }

    private function crearTramite(?array $campos = null): Tramite
    {
        $f = $this->director();
        $this->comoFuncionario($f)->post('/app/tramites', $this->base($campos ?? $this->camposEjemplo()))->assertSessionHasNoErrors();

        return Tramite::query()->latest('id')->firstOrFail();
    }

    private function publicar(Tramite $t): void
    {
        $t->forceFill(['estado' => 'publicado', 'activo' => true])->save();
    }

    public function test_el_editor_guarda_los_campos_de_cada_tipo(): void
    {
        $t = $this->crearTramite();
        $c = $t->campos()->get()->keyBy('clave');
        $this->assertSame(['superficie', 'zona', 'inicio', 'termino', 'sin_lucro', 'dias_total', 'derecho', 'obs', 'servicios'], $c->keys()->all());
        $this->assertEquals(['m²', 2, 500], [$c['superficie']->conf('sufijo'), $c['superficie']->conf('decimales'), $c['superficie']->conf('max')]);
        $this->assertCount(3, $c['zona']->conf('opciones'));
        $this->assertSame('hoy', $c['inicio']->conf('fecha_min'));
        $this->assertTrue($c['superficie']->obligatorio);
        $this->assertFalse($c['derecho']->obligatorio); // los cálculos no se piden
        $this->comoFuncionario($this->director())->get("/app/tramites/{$t->id}")->assertOk()->assertSee('Campos del formulario')->assertSee('Guía de operadores y funciones')->assertSee('superficie');
    }

    public function test_la_clave_se_genera_sola_y_no_se_repite(): void
    {
        $t = $this->crearTramite([['etiqueta' => 'Número de vehículos', 'tipo' => 'numero'], ['etiqueta' => 'Número de vehículos', 'tipo' => 'numero'], ['etiqueta' => 'Hoy', 'tipo' => 'texto']]);
        $this->assertSame(['numero_de_vehiculos', 'numero_de_vehiculos_2', 'hoy_2'], $t->campos()->pluck('clave')->all()); // «hoy» está reservada
    }

    public function test_rechaza_formulas_y_claves_invalidas(): void
    {
        $f = $this->director();
        $envia = fn (array $campos) => $this->comoFuncionario($f)->post('/app/tramites', $this->base($campos));
        $num = ['etiqueta' => 'Monto', 'tipo' => 'numero', 'clave' => 'monto'];
        $calc = fn (string $formula) => ['etiqueta' => 'Total', 'tipo' => 'calculo', 'clave' => 'total', 'formula' => $formula];
        $envia([$num, $calc('monto * ')])->assertSessionHas('error');
        $envia([$num, $calc('system("ls")')])->assertSessionHas('error');
        $envia([$num, $calc('monto + desconocido')])->assertSessionHas('error');
        $envia([$num, $calc('total * 2')])->assertSessionHas('error'); // a sí mismo
        $envia([$num, ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'clave' => 'nombre'], $calc('nombre + 1')])->assertSessionHas('error'); // un texto no se calcula
        $envia([$num, ['etiqueta' => 'A', 'tipo' => 'calculo', 'clave' => 'a', 'formula' => 'b + 1'], ['etiqueta' => 'B', 'tipo' => 'calculo', 'clave' => 'b', 'formula' => 'a + 1']])->assertSessionHas('error'); // círculo
        $envia([$num, ['etiqueta' => 'Otro', 'tipo' => 'numero', 'clave' => 'monto']])->assertSessionHas('error'); // clave repetida
        $envia([['etiqueta' => 'X', 'tipo' => 'numero', 'clave' => 'Mal Clave']])->assertSessionHas('error');
        $envia([['etiqueta' => 'X', 'tipo' => 'numero', 'clave' => 'si']])->assertSessionHas('error'); // reservada
        $envia([['etiqueta' => 'Lista', 'tipo' => 'lista', 'opciones' => 'solo una']])->assertSessionHas('error');
        $envia([['etiqueta' => 'Lista', 'tipo' => 'lista', 'clave' => 'l', 'opciones' => "a;A\na;B"]])->assertSessionHas('error'); // valor repetido
        $envia([['etiqueta' => 'Num', 'tipo' => 'numero', 'min' => '10', 'max' => '1']])->assertSessionHas('error');
        $envia([['etiqueta' => 'Fecha', 'tipo' => 'fecha', 'fecha_min' => 'ayer']])->assertSessionHas('error');
        $envia([['etiqueta' => 'Enlace http://malo.cl', 'tipo' => 'texto']])->assertSessionHas('error');
        $envia(array_map(fn ($i) => ['etiqueta' => 'Campo '.$i, 'tipo' => 'texto'], range(1, 41)))->assertSessionHas('error');
        $this->assertSame(0, TramiteCampo::query()->count());
    }

    public function test_una_lista_con_valores_de_texto_no_puede_usarse_en_una_formula(): void
    {
        $this->comoFuncionario($this->director())->post('/app/tramites', $this->base([
            ['etiqueta' => 'Tipo', 'tipo' => 'lista', 'clave' => 'tipo', 'opciones' => "Casa\nDepartamento"], ['etiqueta' => 'Total', 'tipo' => 'calculo', 'clave' => 'total', 'formula' => 'tipo * 2'],
        ]))->assertSessionHas('error');
    }

    public function test_se_quitan_los_campos_con_la_marca_de_eliminar_y_al_vaciar_el_formulario(): void
    {
        $t = $this->crearTramite();
        $f = $this->director();
        $filas = $t->campos()->get()->map(fn ($c) => ['id' => $c->id, 'clave' => $c->clave, 'etiqueta' => $c->etiqueta, 'tipo' => $c->tipo, 'formula' => $c->conf('formula'),
            'opciones' => collect($c->conf('opciones', []))->map(fn ($o) => $o['valor'].';'.$o['etiqueta'])->implode("\n"), 'eliminar' => $c->clave === 'obs' ? 1 : 0])->all();
        $this->comoFuncionario($f)->post("/app/tramites/{$t->id}", $this->base($filas))->assertSessionHasNoErrors();
        $this->assertFalse($t->campos()->get()->contains('clave', 'obs'));
        $this->comoFuncionario($f)->post("/app/tramites/{$t->id}", ['nombre' => $t->nombre, 'descripcion' => $t->descripcion, 'plazo_dias' => 10, 'campos_enviados' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $t->campos()->count());
        $this->assertSame(9, TramiteCampo::query()->where('tramite_id', $t->id)->count()); // quedan inactivos: las solicitudes antiguas conservan sus respuestas
    }

    public function test_sin_la_marca_del_editor_no_se_toca_el_formulario(): void
    {
        $t = $this->crearTramite();
        $this->comoFuncionario($this->director())->post("/app/tramites/{$t->id}", ['nombre' => 'Nuevo nombre del trámite', 'descripcion' => $t->descripcion, 'plazo_dias' => 10])->assertSessionHasNoErrors();
        $this->assertSame(9, $t->campos()->count());
    }

    public function test_el_vecino_ve_los_campos_y_el_servidor_valida_y_calcula(): void
    {
        $t = $this->crearTramite();
        $this->publicar($t);
        $this->comoCiudadano()->get("/ciudadano/nueva/{$t->id}")->assertOk()->assertSee('Datos de su solicitud')->assertSee('Superficie a ocupar')->assertSee('data-formulario', false)->assertSee('formulario.js', false)->assertSee('Derecho a pagar');
        $datos = ['tramite_id' => $t->id, 'correo' => 'v@example.com', 'detalle' => 'Solicito ocupar un espacio de la costanera para una feria.', 'canal_notificacion' => 'correo', 'consentimiento' => 'si'];
        $ok = ['superficie' => '12.5', 'zona' => '600', 'inicio' => '2026-11-01', 'termino' => '2026-11-30', 'sin_lucro' => '0', 'obs' => 'Feria', 'servicios' => ['agua', 'luz']];
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $datos + ['campos' => $ok])->assertSessionHasNoErrors()->assertRedirect();
        $s = Solicitud::query()->latest('id')->firstOrFail();
        $por = collect($s->datos)->keyBy('clave');
        $this->assertEquals(30, $por['dias_total']['valor']); // 1 al 30 de noviembre, ambos incluidos
        $this->assertEquals(7500, $por['derecho']['valor']);   // 12,5 m² × 600 × 30 / 30
        $this->assertSame('$ 7.500', $por['derecho']['texto']);
        $this->assertSame('12,50 m²', $por['superficie']['texto']);
        $this->assertSame('Costanera', $por['zona']['texto']);
        $this->assertSame('01-11-2026', $por['inicio']['texto']);
        $this->assertSame('Agua, Luz', $por['servicios']['texto']);
        $this->assertSame('No', $por['sin_lucro']['texto']);
        // el cálculo que mande el navegador no vale: el servidor siempre recalcula
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $datos + ['campos' => $ok + ['derecho' => '1', 'dias_total' => '999']])->assertSessionHasNoErrors();
        $this->assertEquals(7500, collect(Solicitud::query()->latest('id')->first()->datos)->keyBy('clave')['derecho']['valor']);
        // sin fines de lucro: el derecho es 0
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $datos + ['campos' => ['sin_lucro' => '1'] + $ok]);
        $this->assertEquals(0, collect(Solicitud::query()->latest('id')->first()->datos)->keyBy('clave')['derecho']['valor']);
    }

    public function test_valida_cada_respuesta_con_un_mensaje_por_campo(): void
    {
        $t = $this->crearTramite();
        $this->publicar($t);
        $datos = ['tramite_id' => $t->id, 'correo' => 'v@example.com', 'detalle' => 'Solicito ocupar un espacio de la costanera para una feria.', 'canal_notificacion' => 'correo', 'consentimiento' => 'si'];
        $ok = ['superficie' => '12', 'zona' => '600', 'inicio' => '2026-11-01', 'termino' => '2026-11-30', 'sin_lucro' => '0'];
        $mal = function (array $cambio, string $campo) use ($datos, $ok) {
            Cache::flush(); // reinicia el límite de envíos por minuto
            $this->comoCiudadano()->post('/ciudadano/solicitudes', $datos + ['campos' => $cambio + $ok]);
            $this->assertTrue(session()->has('errors') && session('errors')->has('campos.'.$campo), 'Debía fallar «'.$campo.'» con '.json_encode($cambio));
        };
        $mal(['superficie' => ''], 'superficie');            // obligatorio
        $mal(['superficie' => 'abc'], 'superficie');         // no es número
        $mal(['superficie' => '0'], 'superficie');           // bajo el mínimo
        $mal(['superficie' => '501'], 'superficie');         // sobre el máximo
        $mal(['superficie' => '1.234'], 'superficie');       // más decimales de los permitidos
        $mal(['zona' => '9999'], 'zona');                    // no es una opción
        $mal(['inicio' => '2026-02-30'], 'inicio');          // fecha inexistente
        $mal(['inicio' => '2026-10-21'], 'inicio');          // antes de hoy
        $mal(['sin_lucro' => '2'], 'sin_lucro');
        $mal(['obs' => str_repeat('x', 101)], 'obs');
        $mal(['servicios' => ['agua', 'luz', 'banos']], 'servicios');   // más de las permitidas
        $mal(['servicios' => ['inventada']], 'servicios');
        $this->assertSame(0, Solicitud::query()->count());
    }

    public function test_los_textos_se_guardan_planos_y_se_muestran_escapados(): void
    {
        $t = $this->crearTramite([['etiqueta' => '<b>Motivo</b> <script>alert(1)</script>', 'tipo' => 'texto', 'clave' => 'motivo']]);
        $this->assertSame('Motivo alert(1)', $t->campos()->first()->etiqueta);
        $this->publicar($t);
        $this->comoCiudadano()->post('/ciudadano/solicitudes', ['tramite_id' => $t->id, 'correo' => 'v@example.com', 'detalle' => 'Solicito ocupar un espacio de la costanera para una feria.', 'canal_notificacion' => 'correo', 'consentimiento' => 'si',
            'campos' => ['motivo' => '<img src=x onerror=alert(1)> hola']])->assertSessionHasNoErrors();
        $s = Solicitud::query()->latest('id')->firstOrFail();
        $this->assertSame('hola', $s->datos[0]['texto']);
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $this->director());
        $this->comoFuncionario($this->director())->get("/app/expedientes/{$e->id}")->assertOk()->assertSee('Datos del formulario')->assertSee('Motivo alert(1)')->assertDontSee('<img src=x', false);
    }

    public function test_las_respuestas_llegan_al_funcionario_al_vecino_al_comprobante_y_a_los_metadatos(): void
    {
        $t = $this->crearTramite();
        $this->publicar($t);
        $this->comoCiudadano()->post('/ciudadano/solicitudes', ['tramite_id' => $t->id, 'correo' => 'v@example.com', 'detalle' => 'Solicito ocupar un espacio de la costanera para una feria.', 'canal_notificacion' => 'correo', 'consentimiento' => 'si',
            'campos' => ['superficie' => '10', 'zona' => '300', 'inicio' => '2026-11-01', 'termino' => '2026-11-10', 'sin_lucro' => '0']])->assertSessionHasNoErrors();
        $s = Solicitud::query()->latest('id')->firstOrFail();
        $f = $this->director();
        $this->comoFuncionario($f)->get("/app/solicitudes/{$s->folio}")->assertOk()->assertSee('Datos del formulario')->assertSee('$ 1.000');
        $this->comoCiudadano()->get("/ciudadano/solicitudes/{$s->folio}")->assertOk()->assertSee('Datos que usted completó')->assertSee('Derecho a pagar');
        $e = app(ExpedienteService::class)->crearDesdeSolicitud($s, $f);
        $f2 = collect($e->metadatos['descripcion']['formulario'])->keyBy('campo');
        $this->assertEquals(1000, $f2['derecho']['valor']);
        $this->assertSame('Rural', $f2['zona']['texto']);
        $html = view('pdf.comprobante', ['s' => $s->load('tramite'), 'anexos' => [], 'codigo' => 'X', 'qr' => '', 'url' => 'https://x'])->render();
        $this->assertStringContainsString('Derecho a pagar', $html);
        $this->assertStringContainsString('$ 1.000', $html);
    }

    public function test_una_solicitud_antigua_o_un_tramite_sin_campos_siguen_igual(): void
    {
        $s = $this->solicitudDOM();
        $this->assertNull($s->datos);
        $this->comoFuncionario($this->usuario('funcionario', 1))->get("/app/solicitudes/{$s->folio}")->assertOk()->assertDontSee('Datos del formulario');
        $this->comoCiudadano()->get('/ciudadano/nueva/1')->assertOk()->assertDontSee('Datos de su solicitud');
    }

    public function test_la_vista_previa_muestra_el_formulario(): void
    {
        $t = $this->crearTramite();
        $this->comoFuncionario($this->director())->get("/app/tramites/{$t->id}/vista")->assertOk()->assertSee('Datos que se le pedirán')->assertSee('data-solo-vista', false)->assertSee('Zona');
    }

    public function test_duplicar_copia_los_campos_y_publicar_exige_un_formulario_sano(): void
    {
        $t = $this->crearTramite();
        $f = $this->director();
        $this->comoFuncionario($f)->post("/app/tramites/{$t->id}/duplicar")->assertSessionHas('ok');
        $copia = Tramite::query()->latest('id')->first();
        $this->assertNotSame($t->id, $copia->id);
        $this->assertSame($t->campos()->pluck('clave')->all(), $copia->campos()->pluck('clave')->all());
        // un listado guardado que quedó sin opciones impide publicar
        $l = ListaOpciones::query()->create(['nombre' => 'Vacía', 'unidad_id' => $f->unidad_id, 'activa' => true]);
        TramiteCampo::query()->create(['tramite_id' => $copia->id, 'clave' => 'v', 'etiqueta' => 'Vacía', 'tipo' => 'lista', 'orden' => 99, 'config' => ['lista_id' => $l->id]]);
        $this->assertNotEmpty(app(TramiteCamposService::class)->problemas($copia));
    }

    public function test_listados_reutilizables_crear_editar_usar_y_desactivar(): void
    {
        $f = $this->director();
        $c = $this->comoFuncionario($f);
        $c->get('/app/tramites/listas')->assertOk()->assertSee('Listados de opciones');
        $c->post('/app/tramites/listas', ['nombre' => 'Tarifas', 'descripcion' => 'Tarifa por zona', 'opciones' => "1000;Centro\n600;Costanera\n300;Rural"])->assertSessionHasNoErrors()->assertSessionHas('ok');
        $l = ListaOpciones::query()->where('nombre', 'Tarifas')->firstOrFail();
        $this->assertSame($f->unidad_id, $l->unidad_id);
        $this->assertSame(3, $l->items()->count());
        $c->post('/app/tramites/listas', ['nombre' => 'tarifas', 'opciones' => "a\nb"])->assertSessionHas('error'); // nombre repetido
        $c->post('/app/tramites/listas', ['nombre' => 'Una sola', 'opciones' => 'solo'])->assertSessionHas('error');
        $c->post('/app/tramites/listas', ['nombre' => 'Con enlace', 'opciones' => "ver http://x.cl\notra"])->assertSessionHas('error');
        // se usa en un trámite, con valores numéricos dentro de un cálculo
        $t = $this->crearTramite([['etiqueta' => 'Zona', 'tipo' => 'lista', 'clave' => 'zona', 'lista_id' => $l->id, 'obligatorio' => 1], ['etiqueta' => 'Doble', 'tipo' => 'calculo', 'clave' => 'doble', 'formula' => 'zona * 2']]);
        $this->publicar($t);
        $datos = ['tramite_id' => $t->id, 'correo' => 'v@example.com', 'detalle' => 'Solicito ocupar un espacio de la costanera para una feria.', 'canal_notificacion' => 'correo', 'consentimiento' => 'si'];
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $datos + ['campos' => ['zona' => '600']])->assertSessionHasNoErrors();
        $this->assertEquals(1200, collect(Solicitud::query()->latest('id')->first()->datos)->keyBy('clave')['doble']['valor']);
        // editar el listado: cambia lo que se ofrece; lo enviado conserva su texto
        $c->post("/app/tramites/listas/{$l->id}", ['nombre' => 'Tarifas', 'opciones' => "1100;Centro\n700;Costanera"])->assertSessionHasNoErrors();
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $datos + ['campos' => ['zona' => '300']])->assertSessionHasErrors('campos.zona');
        $this->assertSame('Costanera', collect(Solicitud::query()->first()->datos)->firstWhere('clave', 'zona')['texto']);
        // desactivado: los trámites que ya lo usan siguen funcionando, pero no se ofrece para campos nuevos
        $c->post("/app/tramites/listas/{$l->id}/estado", ['activa' => 0])->assertSessionHas('ok');
        $this->comoCiudadano()->post('/ciudadano/solicitudes', $datos + ['campos' => ['zona' => '700']])->assertSessionHasNoErrors();
        $c->get("/app/tramites/{$t->id}")->assertOk();
    }

    public function test_los_listados_los_edita_su_direccion_o_la_administracion(): void
    {
        $f1 = $this->director();
        $otro = Usuario::query()->where('rol', 'edit_tramites')->firstOrFail(); // DOM: otra dirección
        $this->assertNotSame($f1->unidad_id, $otro->unidad_id);
        $this->comoFuncionario($f1)->post('/app/tramites/listas', ['nombre' => 'De la dirección uno', 'opciones' => "a\nb"])->assertSessionHasNoErrors();
        $l = ListaOpciones::query()->where('nombre', 'De la dirección uno')->firstOrFail();
        $this->comoFuncionario($otro)->get("/app/tramites/listas/{$l->id}")->assertOk()->assertSee('pertenece a otra dirección');
        $this->comoFuncionario($otro)->post("/app/tramites/listas/{$l->id}", ['nombre' => 'Robada', 'opciones' => "x\ny"])->assertSessionHas('error');
        $this->comoFuncionario($otro)->post("/app/tramites/listas/{$l->id}/estado", ['activa' => 0])->assertSessionHas('error');
        $this->assertSame('De la dirección uno', $l->refresh()->nombre);
        $this->comoFuncionario($this->usuario('admin'))->post("/app/tramites/listas/{$l->id}", ['nombre' => 'De la dirección uno', 'opciones' => "a\nb\nc"])->assertSessionHasNoErrors();
        $this->assertSame(3, $l->items()->count());
        $this->comoFuncionario($this->usuario('auditor'))->get('/app/tramites/listas')->assertForbidden();
    }
}
