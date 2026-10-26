<?php

/* Genera los manuales en PDF: php scripts/build-manuales-pdf.php  (salida: docs/manuales/pdf/*.pdf) */

use App\Support\ManualRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

@mkdir(__DIR__.'/../docs/manuales/pdf', 0755, true);
$r = new ManualRenderer;
$pdf = function (string $archivo, string $titulo, string $html): void {
    $html = preg_replace('#<img[^>]*alt="([^"]*)"[^>]*>#', '<p><em>[Imagen: $1]</em></p>', $html);
    $o = new Options;
    $o->set('isRemoteEnabled', false);
    $o->set('isPhpEnabled', false);
    $o->set('defaultFont', 'DejaVu Sans');
    $o->set('fontCache', storage_path('framework/dompdf'));
    $o->set('tempDir', storage_path('framework/dompdf'));
    @mkdir(storage_path('framework/dompdf'), 0750, true);
    $d = new Dompdf($o);
    $d->loadHtml('<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><title>'.e($titulo).'</title><style>
        body{font-family:"DejaVu Sans",sans-serif;font-size:10.5pt;color:#111;line-height:1.45} h1{color:#006fb3;font-size:20pt} h2{color:#0a2e4d;border-bottom:1px solid #999;margin-top:18pt}
        table{border-collapse:collapse;width:100%} td,th{border:1px solid #888;padding:3pt 5pt;vertical-align:top} th{background:#eef4f9} code,kbd{background:#eee}
        blockquote{border-left:3pt solid #006fb3;background:#eef4f9;margin:8pt 0;padding:4pt 8pt}</style></head><body>'
        .'<p style="font-size:9pt">I. Municipalidad de Santo Domingo · Sistema de Gestión Documental</p>'.$html.'</body></html>', 'UTF-8');
    $d->setPaper('letter');
    $d->render();
    file_put_contents(__DIR__.'/../docs/manuales/pdf/'.$archivo, $d->output());
    echo $archivo."\n";
};
foreach (ManualRenderer::PERFILES as $slug => [$nombre]) {
    $m = $r->render($slug);
    $pdf("manual-$slug.pdf", $m['titulo'], $m['html']);
}
// Manuales de implementación (para quien instala y opera el sistema)
foreach (['LEEME_PRIMERO' => 'implementacion-00-leeme-primero', 'GUIA_INSTALACION' => 'implementacion-01-guia-de-instalacion', 'SEGURIDAD' => 'implementacion-02-seguridad',
    'LIMITACIONES' => 'implementacion-03-limitaciones', 'INCIDENTES' => 'implementacion-04-incidentes', 'NORMATIVA' => 'implementacion-05-normativa'] as $md => $salida) {
    $m = $r->aHtml((string) file_get_contents(__DIR__."/../docs/$md.md"));
    $pdf("$salida.pdf", $m['titulo'], $m['html']);
}
