<?php

namespace Tests;

use App\Services\FileVault;
use App\Services\Firma\FirmaClient;
use App\Support\AeadXChaCha;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AeadXChaCha::$soloAlternativa = (bool) getenv('SGD_TEST_SIN_SODIUM'); // ejercita la suite con la alternativa a sodium
        $dir = sys_get_temp_dir().'/sgd_test_storage_'.getmypid();
        config([
            'sgd.storage_path' => $dir,
            'sgd.file_key_id' => 'k1',
            'sgd.file_keys' => json_encode(['k1' => base64_encode(str_repeat('K', 32)), 'k0' => base64_encode(str_repeat('O', 32))]),
            'sgd.audit_hmac_key' => str_repeat('a', 64),
            'sgd.claveunica.mode' => 'mock',
            'sgd.firmagob.mode' => 'mock',
        ]);
        app()->forgetInstance(FileVault::class);
        app()->forgetInstance(FirmaClient::class);
    }

    protected function tearDown(): void
    {
        $dir = config('sgd.storage_path');
        if (is_string($dir) && str_contains($dir, 'sgd_test_storage_') && is_dir($dir)) {
            exec('rm -rf '.escapeshellarg($dir));
        }
        parent::tearDown();
    }
}
