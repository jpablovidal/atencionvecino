<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Solicitud;
use App\Services\Firma\FirmaRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    public function __construct(private readonly QrService $qr, private readonly FirmaRenderer $firmas) {}

    public function html(string $html, ?string $tituloPie = null): string
    {
        return $this->render('pdf.base', ['contenido' => $html, 'pie' => $tituloPie]);
    }

    /** PDF de un borrador/versión (sin número aún). */
    public function documento(Documento $d, string $html, int $version): string
    {
        return $this->render('pdf.documento', ['doc' => $d, 'contenido' => $html, 'version' => $version, 'copia' => false]);
    }

    /** Copia pública: documento + sellos de firma configurados + hoja de certificado. */
    public function copiaPublica(Documento $d, string $sha256Version): string
    {
        return $this->aPdf($this->htmlCopiaPublica($d, $sha256Version));
    }

    public function htmlCopiaPublica(Documento $d, string $sha256Version): string
    {
        $url = url('/validar/'.$d->codigo_validacion);

        return view('pdf.documento', [
            'doc' => $d->loadMissing(['tipo', 'pasos.usuario', 'expediente']), 'contenido' => $d->html, 'version' => $d->version_actual,
            'copia' => true, 'sha' => $sha256Version, 'url' => $url, 'qr' => $this->qr->dataUri($url), 'sellos' => $this->firmas->html($d),
        ])->render();
    }

    /** PDF de prueba con datos ficticios para la vista previa de estilos y posiciones. */
    public function pruebaFirmas(?int $tipoId): string
    {
        return $this->aPdf(view('pdf.prueba_firmas', ['sellos' => $this->firmas->muestra($tipoId, 2)])->render());
    }

    /** @param array<int,array{nombre:string,sha256:string,tamano:int}> $anexos */
    public function comprobante(Solicitud $s, array $anexos, string $codigo): string
    {
        $url = url('/validar-comprobante/'.$s->folio.'/'.$codigo);

        return $this->render('pdf.comprobante', ['s' => $s->loadMissing('tramite'), 'anexos' => $anexos, 'codigo' => $codigo, 'qr' => $this->qr->dataUri($url), 'url' => $url]);
    }

    private function render(string $vista, array $datos): string
    {
        return $this->aPdf(view($vista, $datos)->render());
    }

    private function aPdf(string $html): string
    {
        $o = new Options;
        $o->set('isRemoteEnabled', false);
        $o->set('isPhpEnabled', false);
        $o->set('isJavascriptEnabled', false);
        $o->set('chroot', [resource_path('views/pdf')]);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('fontCache', storage_path('framework/dompdf'));
        $o->set('tempDir', storage_path('framework/dompdf'));
        @mkdir(storage_path('framework/dompdf'), 0750, true);
        $dompdf = new Dompdf($o);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
