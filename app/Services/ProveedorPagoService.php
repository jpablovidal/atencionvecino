<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\DecretoPago;
use App\Models\Egreso;
use App\Support\Run;
use Illuminate\Support\Carbon;

/**
 * Consulta de situación de pago para proveedores (pública). Exige los TRES datos de la factura: RUT de la empresa, número y fecha;
 * si no coinciden todos, la respuesta es la misma («sin información») para no revelar qué datos existen. Solo entrega lo necesario para el
 * proveedor: estado, folio del decreto, montos y datos del pago; nunca expedientes, nombres de funcionarios ni cuentas bancarias.
 */
class ProveedorPagoService
{
    /** Número de factura comparable: sin espacios ni símbolos, en mayúsculas y sin ceros a la izquierda. */
    public static function normalizarFactura(string $f): string
    {
        $n = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $f) ?? '');

        return ltrim($n, '0') === '' ? $n : ltrim($n, '0');
    }

    /**
     * @return array<int,array<string,mixed>> situaciones (vacío si no hay coincidencia completa)
     *
     * @throws FlujoException datos mal ingresados (RUT inválido, fecha inválida, factura vacía)
     */
    public function consultar(string $rut, string $factura, string $fecha): array
    {
        $run = Run::normalizar($rut) ?? throw new FlujoException('El RUT de la empresa no es válido. Revise el número y el dígito verificador.');
        $nFactura = self::normalizarFactura($factura);
        if ($nFactura === '' || strlen($nFactura) > 40) {
            throw new FlujoException('Ingrese el número de la factura.');
        }
        try {
            $f = Carbon::createFromFormat('Y-m-d', $fecha);
        } catch (\Throwable) {
            throw new FlujoException('Ingrese una fecha de factura válida.');
        }
        if (! $f || $f->format('Y-m-d') !== $fecha || $f->year < 2000 || $f->gt(now('America/Santiago')->addDay())) {
            throw new FlujoException('Ingrese una fecha de factura válida.');
        }
        $decretos = DecretoPago::query()->with('documento', 'egresos.documento')->where('proveedor_run', $run)->whereDate('factura_fecha', $fecha)->get()
            ->filter(fn ($d) => self::normalizarFactura((string) $d->factura) === $nFactura);

        return $decretos->map(fn (DecretoPago $d) => $this->situacion($d))->values()->all();
    }

    /** @return array<string,mixed> */
    public function situacion(DecretoPago $d): array
    {
        $fase = $d->fase();
        $egresos = $d->egresos->filter(fn (Egreso $e) => $e->fase() === 'emitido');
        $pagado = (int) $egresos->sum('monto');
        $dias = $d->factura_fecha ? (int) $d->factura_fecha->startOfDay()->diffInDays(now('America/Santiago')->startOfDay(), false) : null;
        [$estado, $mensaje] = match ($fase) {
            'borrador' => ['En revisión', 'Su factura está en revisión: aún no se emite el decreto de pago.'],
            'en_firma' => ['Decreto en firma', 'El decreto de pago está en visación y firma.'],
            'emitido' => $pagado > 0
                ? ['Pagada parcialmente', 'Se han pagado $'.number_format($pagado, 0, ',', '.').' de $'.number_format($d->monto, 0, ',', '.').'. El saldo está pendiente de pago por Tesorería.']
                : ['Decreto emitido', 'El decreto de pago está emitido. El pago está pendiente de egreso por Tesorería.'],
            'pagado' => ['Pagada', 'La factura fue pagada.'],
            default => ['Decreto anulado', 'El decreto de pago asociado fue anulado. Comuníquese con la Dirección de Administración y Finanzas.'],
        };

        return ['estado' => $estado, 'clave' => $fase, 'mensaje' => $mensaje, 'proveedor' => $d->proveedor_nombre, 'factura' => $d->factura, 'factura_fecha' => $d->factura_fecha?->format('d-m-Y'), 'dias' => $dias,
            'decreto' => $d->emitido() ? $d->folio() : null, 'monto' => $d->monto, 'pagado' => $pagado,
            'pagos' => $egresos->map(fn (Egreso $e) => ['fecha' => $e->fecha->format('d-m-Y'), 'medio' => Egreso::MEDIOS[$e->medio_pago] ?? $e->medio_pago, 'monto' => $e->monto, 'referencia' => $e->referencia, 'egreso' => $e->documento?->numeroFormateado()])->values()->all()];
    }
}
