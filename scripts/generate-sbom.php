<?php

/* SBOM CycloneDX 1.5 desde composer.lock + assets front-end autoalojados. Uso: php scripts/generate-sbom.php > docs/sbom.cdx.json */

$lock = json_decode((string) file_get_contents(__DIR__.'/../composer.lock'), true);
$comp = [];
foreach (['packages' => 'prod', 'packages-dev' => 'dev'] as $k => $scope) {
    foreach ($lock[$k] ?? [] as $p) {
        $v = ltrim($p['version'], 'v');
        $comp[] = [
            'type' => 'library', 'bom-ref' => 'pkg:composer/'.$p['name'].'@'.$v, 'name' => $p['name'], 'version' => $v,
            'purl' => 'pkg:composer/'.$p['name'].'@'.$v, 'scope' => $scope === 'prod' ? 'required' : 'optional',
            'licenses' => array_map(fn ($l) => ['license' => ['id' => $l]], $p['license'] ?? []),
        ];
    }
}
foreach ([['tinymce', '7.9.3', 'GPL-2.0-or-later'], ['@gobdigital-cl/gob.cl', '3.0.10', 'MIT'], ['bootstrap (incluido en gob.cl)', '4.6', 'MIT'],
    ['jquery', '3.7.1', 'MIT'], ['bootstrap.bundle (popper.js)', '4.6.2', 'MIT'], ['@fontsource/roboto', '5.x', 'Apache-2.0'], ['@fontsource/roboto-slab', '5.x', 'Apache-2.0'],
    ['tinymce-i18n (es)', '25.x', 'ver LICENSE del paquete (A VALIDAR)']] as [$n, $v, $l]) {
    $comp[] = ['type' => 'library', 'bom-ref' => 'npm:'.$n, 'name' => $n, 'version' => $v, 'scope' => 'required', 'licenses' => [['license' => ['name' => $l]]]];
}
echo json_encode([
    'bomFormat' => 'CycloneDX', 'specVersion' => '1.5', 'version' => 1,
    'metadata' => ['timestamp' => gmdate('c'), 'component' => ['type' => 'application', 'name' => 'sgd-santo-domingo', 'version' => '1.0.0']],
    'components' => $comp,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
