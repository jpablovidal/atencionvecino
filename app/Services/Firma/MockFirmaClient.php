<?php

namespace App\Services\Firma;

/** SOLO DESARROLLO. Marca la firma como simulada ("FIRMA SIMULADA – sin validez legal"). Prohibido en producción. */
class MockFirmaClient implements FirmaClient
{
    public function firmar(string $pdf, string $run, ?string $otp, string $descripcion): array
    {
        if ($otp === null || ! preg_match('/^\d{6}$/', $otp)) {
            throw new FirmaException('Debe ingresar el código OTP de 6 dígitos.');
        }
        if ($otp === '000000') {
            throw new FirmaException('El código OTP es inválido o expiró.'); // permite probar la falla
        }

        return ['pdf' => $pdf, 'checksum_original' => hash('sha256', $pdf), 'simulada' => true];
    }
}
