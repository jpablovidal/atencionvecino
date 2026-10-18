<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\DecretoPago;
use App\Models\Egreso;
use App\Models\Usuario;
use App\Support\MontoEnLetras;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Egreso de Tesorería: lo genera el tesorero municipal para un decreto de pago emitido (fecha, medio de pago, monto y detalle). El documento
 * se envía a firma con los firmantes configurados del egreso; cada firmante usa firma electrónica simple o avanzada según su configuración.
 * No se puede egresar más que el monto del decreto (los egresos en curso cuentan).
 */
class EgresoService
{
    public function __construct(private readonly AuditoriaService $audit, private readonly DocumentoService $docs, private readonly PagoConfigService $config) {}

    private function exigir(Usuario $u): void
    {
        if (! $u->tienePermiso('egresos.gestionar')) {
            throw new FlujoException('No tiene permiso para gestionar egresos.');
        }
    }

    /** @param array<string,mixed> $x */
    public function crear(Usuario $u, DecretoPago $d, array $x): Egreso
    {
        $this->exigir($u);
        $d->load('egresos', 'documento', 'expediente', 'compra');
        if (! $d->emitido()) {
            throw new FlujoException('El decreto de pago aún no está emitido (firmado o cargado): no se puede egresar.');
        }
        $medio = (string) ($x['medio_pago'] ?? '');
        if (! isset(Egreso::MEDIOS[$medio])) {
            throw new FlujoException('Elija el medio de pago.');
        }
        $monto = $x['monto'] ?? null;
        if (! is_numeric($monto) || (string) (int) $monto !== (string) $monto || (int) $monto < 1) {
            throw new FlujoException('El monto debe ser un número entero de pesos mayor que cero.');
        }
        $saldo = $d->monto - $d->comprometido();
        if ((int) $monto > $saldo) {
            throw new FlujoException('El monto excede el saldo por egresar del decreto ($'.number_format(max(0, $saldo), 0, ',', '.').').');
        }
        try {
            $fecha = Carbon::createFromFormat('Y-m-d', (string) ($x['fecha'] ?? ''), 'America/Santiago')->startOfDay();
            if ($fecha->format('Y-m-d') !== (string) $x['fecha']) {
                throw new \InvalidArgumentException('fecha');
            }
        } catch (\Throwable) {
            throw new FlujoException('Fecha del egreso inválida.');
        }
        if ($fecha->gt(now('America/Santiago')->endOfDay())) {
            throw new FlujoException('La fecha del egreso no puede ser futura.');
        }
        $detalle = mb_substr(TramiteService::limpiarTexto((string) ($x['detalle'] ?? ''), true), 0, 1000);
        if (mb_strlen($detalle) < 5) {
            throw new FlujoException('Indique el detalle del egreso (mínimo 5 caracteres).');
        }
        $ref = mb_substr(TramiteService::limpiarTexto((string) ($x['referencia'] ?? '')), 0, 80) ?: null;
        $cuenta = mb_substr(TramiteService::limpiarTexto((string) ($x['cuenta'] ?? '')), 0, 120) ?: null;
        $flujo = $this->config->flujo('egreso');
        $tipo = $this->config->tipo('egreso');

        return DB::transaction(function () use ($u, $d, $medio, $monto, $fecha, $detalle, $ref, $cuenta, $flujo, $tipo) {
            $e = Egreso::query()->create(['decreto_pago_id' => $d->id, 'fecha' => $fecha->format('Y-m-d'), 'medio_pago' => $medio, 'monto' => (int) $monto, 'detalle' => $detalle, 'referencia' => $ref, 'cuenta' => $cuenta,
                'estado' => 'abierto', 'creado_por' => $u->id]);
            try {
                $doc = $this->docs->crear($d->expediente, $tipo, null, 'Egreso — '.$d->proveedor_nombre, $u, [], $this->html($d, $e));
            } catch (FlujoException $ex) {
                throw new FlujoException($ex->getMessage().' (reabra el expediente de pago para registrar el egreso).');
            }
            $this->docs->definirFlujo($doc, $flujo['flujo'], $flujo['visadores'], $flujo['firmantes'], $flujo['permitidas']);
            $e->forceFill(['documento_id' => $doc->id])->save();
            $this->audit->registrar('egreso.crear', 'egreso', $e->id, ['decreto' => $d->id, 'monto' => $e->monto, 'medio' => $medio]);

            return $e;
        });
    }

    /** Contenido del egreso compuesto por el sistema: todos los valores se escapan. */
    public function html(DecretoPago $d, Egreso $e): string
    {
        $q = fn (string $v) => e($v);
        $filas = [
            ['Fecha del egreso', $e->fecha->format('d-m-Y')],
            ['Decreto de pago', (string) ($d->folio() ?? '—')],
            ['Expediente de pago', $d->expediente->codigo],
            ['Proveedor', $d->proveedor_nombre.($d->proveedor_run ? ' (RUT '.$d->proveedor_run.')' : '')],
            ['Concepto', $d->concepto],
            ['Medio de pago', Egreso::MEDIOS[$e->medio_pago]],
            ['Monto', '$'.number_format($e->monto, 0, ',', '.').' ('.MontoEnLetras::pesos($e->monto).')'],
            ['Referencia', $e->referencia ?: '—'],
            ['Cuenta o banco', $e->cuenta ?: '—'],
            ['Imputación', $d->imputacion ?: '—'],
        ];
        $t = '<table style="width:100%;border-collapse:collapse"><tbody>';
        foreach ($filas as [$k, $v]) {
            $t .= '<tr><th style="text-align:left;width:32%;border:1px solid #999;padding:3px 6px">'.$q($k).'</th><td style="border:1px solid #999;padding:3px 6px">'.$q((string) $v).'</td></tr>';
        }

        return '<h2>Comprobante de egreso</h2><p>Santo Domingo, '.$q(now('America/Santiago')->format('d-m-Y')).'.</p>'.$t.'</tbody></table><p><strong>Detalle del egreso:</strong></p><p>'.nl2br($q($e->detalle)).'</p>';
    }

    public function anular(Usuario $u, Egreso $e, string $motivo): void
    {
        $this->exigir($u);
        $motivo = mb_substr(TramiteService::limpiarTexto($motivo), 0, 300);
        if (mb_strlen($motivo) < 5) {
            throw new FlujoException('Indique el motivo de la anulación.');
        }
        DB::transaction(function () use ($e, $motivo) {
            $e = Egreso::query()->lockForUpdate()->with('documento')->findOrFail($e->id);
            if ($e->estado !== 'abierto' || $e->fase() !== 'en_firma') {
                throw new FlujoException('Solo se anulan egresos que aún están en firma; uno emitido queda como respaldo.');
            }
            $doc = $e->documento;
            if ($doc) {
                $doc->pasos()->where('estado', 'pendiente')->update(['estado' => 'rechazado', 'comentario' => 'Egreso anulado']);
                $doc->forceFill(['estado' => 'anulado'])->save();
            }
            $e->forceFill(['estado' => 'anulado', 'anulado_motivo' => $motivo])->save();
            $this->audit->registrar('egreso.anular', 'egreso', $e->id);
        });
    }
}
