<?php

namespace Tests\Support;

use App\Models\Documento;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\DocumentoService;
use App\Services\SolicitudService;
use App\Support\Run;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;

/** Utilidades de prueba: datos demo, archivos válidos y sesiones simuladas. */
trait Escenario
{
    protected function sembrar(): void
    {
        $this->seed(DemoSeeder::class);
    }

    protected function usuario(string $rol, int $n = 1): Usuario
    {
        return Usuario::query()->where('rol', $rol)->orderBy('id')->skip($n - 1)->firstOrFail();
    }

    protected function pdfValido(string $extra = ''): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n$extra\ntrailer<</Root 1 0 R>>\n%%EOF";
    }

    protected function archivo(string $nombre = 'a.pdf', ?string $bytes = null): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($p, $bytes ?? $this->pdfValido());

        return new UploadedFile($p, $nombre, 'application/pdf', null, true);
    }

    protected function ciudadano(): array
    {
        return ['run' => '20000001-'.Run::dv('20000001'), 'nombres' => 'Vecina', 'apellidos' => 'De Prueba'];
    }

    protected function solicitudDOM(): Solicitud
    {
        $t = Tramite::query()->with('anexos')->findOrFail(1);

        return app(SolicitudService::class)->ingresar($this->ciudadano(), $t, 'vecina@example.com', 'Solicito permiso de edificación para mi terreno.', 'correo',
            [1 => $this->archivo('planos.pdf'), 2 => $this->archivo('dominio.pdf')], []);
    }

    protected function comoCiudadano(): static
    {
        return $this->withSession(['ciudadano' => $this->ciudadano(), 'login_at' => time(), 'last_seen' => time()]);
    }

    protected function comoFuncionario(Usuario $u): static
    {
        return $this->actingAs($u)->withSession(['login_at' => time(), 'last_seen' => time()]);
    }

    protected function documentoVigente($e, $autor, string $tipoCodigo = 'OFICIO'): Documento
    {
        $ds = app(DocumentoService::class);
        $tipo = TipoDocumento::query()->where('codigo', $tipoCodigo)->first();
        $doc = $ds->crear($e, $tipo, null, 'Doc '.$tipoCodigo, $autor);
        $sec = $this->usuario('secretario');
        $ds->definirFlujo($doc, 'agil', [], [$sec->id]);

        return $ds->firmar($doc->refresh(), $sec, '111222');
    }
}
