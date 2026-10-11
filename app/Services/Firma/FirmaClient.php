<?php

namespace App\Services\Firma;

interface FirmaClient
{
    /**
     * Firma un PDF. NUNCA debe almacenar ni registrar el OTP.
     *
     * @return array{pdf:string,checksum_original:string,simulada:bool}
     *
     * @throws FirmaException si la firma no se pudo completar (el flujo no avanza)
     */
    public function firmar(string $pdf, string $run, ?string $otp, string $descripcion): array;
}
