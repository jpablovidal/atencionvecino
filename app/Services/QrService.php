<?php

namespace App\Services;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrService
{
    /** QR como data URI PNG (sin servicios externos). */
    public function dataUri(string $texto): string
    {
        $o = new QROptions([
            'outputType' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 5,
            'addQuietzone' => true,
        ]);

        return (new QRCode($o))->render($texto);
    }
}
