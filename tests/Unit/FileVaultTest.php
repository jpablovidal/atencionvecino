<?php

namespace Tests\Unit;

use App\Services\FileVault;
use Tests\TestCase;

class FileVaultTest extends TestCase
{
    private function vault(string $active = 'k1'): FileVault
    {
        return new FileVault(config('sgd.storage_path'), $active, ['k1' => str_repeat('K', 32), 'k0' => str_repeat('O', 32)]);
    }

    public function test_ida_y_vuelta_y_el_disco_no_contiene_texto_plano(): void
    {
        $plain = "%PDF-1.4 secreto-ciudadano-12345678\n%%EOF";
        $r = $this->vault()->put($plain);
        $this->assertSame(hash('sha256', $plain), $r['sha256']);
        $this->assertSame($plain, $this->vault()->get($r['ruta'], $r['sha256']));
        $disco = file_get_contents(config('sgd.storage_path').'/'.$r['ruta']);
        $this->assertStringNotContainsString('secreto-ciudadano', $disco);
        $this->assertMatchesRegularExpression('#^[0-9a-f]{2}/[0-9a-f]{38}$#', $r['ruta']); // nombre aleatorio
    }

    public function test_nonce_aleatorio_mismo_contenido_distinto_cifrado(): void
    {
        $a = $this->vault()->put('igual');
        $b = $this->vault()->put('igual');
        $this->assertNotSame($a['ruta'], $b['ruta']);
        $this->assertNotSame(file_get_contents(config('sgd.storage_path').'/'.$a['ruta']), file_get_contents(config('sgd.storage_path').'/'.$b['ruta']));
    }

    public function test_detecta_alteracion_del_archivo(): void
    {
        $r = $this->vault()->put('contenido original largo para alterar');
        $p = config('sgd.storage_path').'/'.$r['ruta'];
        $b = file_get_contents($p);
        $b[strlen($b) - 3] = $b[strlen($b) - 3] ^ "\x01";
        file_put_contents($p, $b);
        $this->expectException(\RuntimeException::class);
        $this->vault()->get($r['ruta'], $r['sha256']);
    }

    public function test_el_hash_en_datos_asociados_impide_sustituir_el_registro(): void
    {
        $r = $this->vault()->put('uno');
        $this->expectException(\Throwable::class);
        $this->vault()->get($r['ruta'], hash('sha256', 'otro'));
    }

    public function test_rotacion_de_claves_las_antiguas_siguen_descifrando(): void
    {
        $r = $this->vault('k0')->put('cifrado con clave antigua');
        $this->assertSame('cifrado con clave antigua', $this->vault('k1')->get($r['ruta'], $r['sha256']));
        $solo = new FileVault(config('sgd.storage_path'), 'k1', ['k1' => str_repeat('K', 32)]);
        $this->expectException(\RuntimeException::class);
        $solo->get($r['ruta'], $r['sha256']);
    }

    public function test_path_traversal_rechazado(): void
    {
        foreach (['../../.env', '../etc/passwd', 'aa/../../x', '/etc/passwd', 'zz/'.str_repeat('a', 38)] as $ruta) {
            try {
                $this->vault()->get($ruta, str_repeat('0', 64));
                $this->fail("debió rechazar $ruta");
            } catch (\RuntimeException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_sin_clave_configurada_no_arranca(): void
    {
        $this->expectException(\RuntimeException::class);
        new FileVault('/tmp', 'k9', ['k1' => 'x']);
    }
}
