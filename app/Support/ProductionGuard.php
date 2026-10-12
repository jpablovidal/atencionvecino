<?php

namespace App\Support;

use RuntimeException;

/** En producción la aplicación FALLA CERRADA si la configuración es insegura. */
class ProductionGuard
{
    public static function verificar(): void
    {
        if (! app()->isProduction() || app()->runningInConsole() && ! app()->runningUnitTests() && in_array($_SERVER['argv'][1] ?? '', ['list', 'help', 'about'], true)) {
            return;
        }
        $e = [];
        $pruebas = Ambiente::esPruebas();
        if (! in_array(config('sgd.ambiente'), ['produccion', 'pruebas'], true)) {
            $e[] = 'SGD_AMBIENTE debe ser produccion o pruebas';
        }
        if ($pruebas && ! Ambiente::pruebasHasta()) {
            $e[] = 'SGD_AMBIENTE=pruebas exige SGD_ACCESO_PRUEBAS_HASTA=AAAA-MM-DD (el acceso de pruebas vence solo)';
        }
        if (config('app.debug')) {
            $e[] = 'APP_DEBUG debe ser false';
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $e[] = 'APP_URL debe ser https';
        }
        if (! $pruebas && config('sgd.claveunica.mode') === 'mock') {
            $e[] = 'CLAVEUNICA_MODE=mock está prohibido';
        }
        if (! $pruebas && config('sgd.firmagob.mode') === 'mock') {
            $e[] = 'FIRMAGOB_MODE=mock está prohibido';
        }
        if (strlen((string) config('sgd.audit_hmac_key')) < 32) {
            $e[] = 'AUDIT_HMAC_KEY ausente o corta';
        }
        if (! config('sgd.file_keys')) {
            $e[] = 'FILE_ENCRYPTION_KEYS ausente';
        }
        if (! config('app.key')) {
            $e[] = 'APP_KEY ausente';
        }
        if (! $pruebas && in_array(config('sgd.docdigital.mode'), ['mock', 'demo'], true)) {
            $e[] = 'DOCDIGITAL_MODE mock/demo está prohibido';
        }
        if (config('sgd.docdigital.mode') === 'prod' && (! config('sgd.docdigital.url_prod') || ! config('sgd.docdigital.api_token'))) {
            $e[] = 'DocDigital prod sin URL/token';
        }
        if (! $pruebas && config('sgd.pisee.mode') === 'mock') {
            $e[] = 'PISEE_MODE=mock está prohibido';
        }
        if (config('sgd.pisee.mode') === 'api' && ! str_starts_with((string) config('sgd.pisee.base_url'), 'https://')) {
            $e[] = 'PISEE_BASE_URL debe ser https';
        }
        if (config('sgd.llm.enabled') && ! config('sgd.llm.api_key')) {
            $e[] = 'LLM_ENABLED sin LLM_API_KEY';
        }
        if (config('mail.default') === 'gmail_api' && (! config('sgd.google.service_account_json') || ! is_file((string) config('sgd.google.service_account_json')))) {
            $e[] = 'GOOGLE_SERVICE_ACCOUNT_JSON no existe';
        }
        if ($e) {
            throw new RuntimeException('Configuración insegura para producción: '.implode('; ', $e));
        }
    }
}
