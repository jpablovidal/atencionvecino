<?php

namespace App\Services;

use App\Models\DecretoPago;
use App\Models\Egreso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tablero de pagos (Tesorería): decretos por egresar, antigüedad de las facturas, egresos en firma, pagado del mes y por medio de pago.
 * Los montos son pesos enteros. El plazo de 30 días para pagar las facturas (Ley 21.131) es referencial: A VALIDAR.
 */
class PagosTableroService
{
    public const PLAZO_DIAS = 30;

    private const TZ = 'America/Santiago';

    private function pesos(int $n): string
    {
        return '$'.number_format($n, 0, ',', '.');
    }

    /** Decretos emitidos con saldo por egresar, con sus días desde la factura (o desde el decreto, si no hay fecha de factura). @return Collection<int,array<string,mixed>> */
    public function porEgresar(): Collection
    {
        $hoy = now(self::TZ)->startOfDay();

        return DecretoPago::query()->with('documento', 'egresos.documento', 'expediente:id,codigo')->where('estado', 'abierto')->get()
            ->filter(fn (DecretoPago $d) => $d->emitido() && $d->monto - $d->egresado() > 0)
            ->map(function (DecretoPago $d) use ($hoy) {
                $base = $d->factura_fecha ?? $d->documento?->fecha_firma ?? $d->fecha;
                $dias = $base ? (int) Carbon::parse($base)->startOfDay()->diffInDays($hoy, false) : null;

                return ['decreto' => $d, 'saldo' => $d->monto - $d->egresado(), 'dias' => $dias, 'base' => $d->factura_fecha ? 'factura' : 'decreto', 'vencido' => $dias !== null && $dias > self::PLAZO_DIAS];
            })->sortByDesc(fn ($f) => $f['dias'] ?? -1)->values();
    }

    /**
     * @return array{mes:string,mesEtiqueta:string,kpis:array<int,array<string,mixed>>,bloques:array<int,array<string,mixed>>,porEgresar:Collection<int,array<string,mixed>>}
     */
    public function datos(?string $mes = null): array
    {
        $ini = ($mes && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) ? Carbon::createFromFormat('Y-m-d', $mes.'-01', self::TZ)->startOfDay() : now(self::TZ)->startOfMonth();
        $fin = $ini->copy()->endOfMonth();
        $todos = DecretoPago::query()->with('documento', 'egresos.documento')->get();
        $abiertos = $todos->where('estado', 'abierto');
        $por = $this->porEgresar();
        $egresosMes = $todos->flatMap->egresos->filter(fn (Egreso $e) => $e->fase() === 'emitido' && $e->fecha->betweenIncluded($ini->copy()->startOfDay(), $fin));
        $enFirma = $todos->flatMap->egresos->filter(fn (Egreso $e) => $e->fase() === 'en_firma');
        $vencidos = $por->where('vencido', true);
        $kpis = [
            ['icono' => 'inbox', 'valor' => $por->count(), 'etiqueta' => 'Decretos por egresar ('.$this->pesos((int) $por->sum('saldo')).')', 'nivel' => $por->isNotEmpty() ? 'info' : 'ok', 'url' => route('app.pagos.tablero')],
            ['icono' => 'alert', 'valor' => $vencidos->count(), 'etiqueta' => 'Con más de '.self::PLAZO_DIAS.' días sin pagar ('.$this->pesos((int) $vencidos->sum('saldo')).')', 'nivel' => $vencidos->isNotEmpty() ? 'aviso' : 'ok', 'url' => route('app.pagos.tablero')],
            ['icono' => 'pen', 'valor' => $enFirma->count(), 'etiqueta' => 'Egresos en firma ('.$this->pesos((int) $enFirma->sum('monto')).')', 'nivel' => $enFirma->isNotEmpty() ? 'info' : 'ok', 'url' => route('app.pagos.tablero')],
            ['icono' => 'check-circle', 'valor' => $egresosMes->count(), 'etiqueta' => 'Egresos del mes ('.$this->pesos((int) $egresosMes->sum('monto')).')', 'nivel' => 'ok', 'url' => route('app.pagos.tablero', ['mes' => $ini->format('Y-m')])],
        ];
        $fases = [];
        foreach (DecretoPago::FASES as $k => $v) {
            $fases[] = [$v, $todos->filter(fn ($d) => $d->fase() === $k)->count(), route('app.pagos', ['fase' => $k])];
        }
        $tramos = [['Hasta 15 días', 0, 15], ['16 a 30 días', 16, 30], ['31 a 60 días', 31, 60], ['Más de 60 días', 61, 99999]];
        $antig = array_map(fn ($t) => [$t[0], $por->filter(fn ($f) => $f['dias'] !== null && $f['dias'] >= $t[1] && $f['dias'] <= $t[2])->count(), null], $tramos);
        $antig[] = ['Sin fecha de referencia', $por->whereNull('dias')->count(), null];
        $medios = [];
        foreach (Egreso::MEDIOS as $k => $v) {
            $g = $egresosMes->where('medio_pago', $k);
            if ($g->isNotEmpty()) {
                $medios[] = [$v.' ('.$this->pesos((int) $g->sum('monto')).')', $g->count(), null];
            }
        }
        $proveedores = $por->groupBy(fn ($f) => $f['decreto']->proveedor_nombre)->map(fn ($g, $n) => [$n.' ('.$this->pesos((int) $g->sum('saldo')).')', $g->count(), null])->sortByDesc(1)->values()->take(8)->all();
        $bloques = [
            ['tipo' => 'barras', 'titulo' => 'Decretos de pago por estado', 'filas' => $fases, 'max' => max(1, ...array_column($fases, 1)), 'nota' => null],
            ['tipo' => 'barras', 'titulo' => 'Antigüedad de lo por pagar (días desde la factura)', 'filas' => $antig, 'max' => max(1, ...array_column($antig, 1)), 'nota' => 'Plazo de pago de '.self::PLAZO_DIAS.' días (Ley 21.131): referencial, A VALIDAR.'],
            ['tipo' => 'barras', 'titulo' => 'Egresos del mes por medio de pago', 'filas' => $medios, 'max' => max(1, ...($medios ? array_column($medios, 1) : [1])), 'nota' => null],
            ['tipo' => 'barras', 'titulo' => 'Proveedores con más decretos por egresar', 'filas' => $proveedores, 'max' => max(1, ...($proveedores ? array_column($proveedores, 1) : [1])), 'nota' => null],
            ['tipo' => 'tabla', 'titulo' => 'Decretos por egresar (los 10 más antiguos)', 'cabeceras' => ['Decreto', 'Proveedor', 'Factura', 'Días', 'Saldo'], 'vacio' => 'No hay decretos por egresar.',
                'filas' => $por->take(10)->map(fn ($f) => [[$f['decreto']->folio() ?? '#'.$f['decreto']->id, route('app.pagos.ver', $f['decreto'])], $f['decreto']->proveedor_nombre, $f['decreto']->factura ?: '—',
                    $f['dias'] === null ? '—' : $f['dias'].($f['vencido'] ? ' ⚠' : ''), $this->pesos($f['saldo'])])->all()],
        ];

        return ['mes' => $ini->format('Y-m'), 'mesEtiqueta' => ucfirst($ini->locale('es')->translatedFormat('F Y')), 'kpis' => $kpis, 'bloques' => $bloques, 'porEgresar' => $por];
    }

    /** CSV (UTF-8 con BOM) de los decretos por egresar; neutraliza fórmulas de planilla (=, +, -, @). */
    public function csv(): string
    {
        $celda = fn ($v) => '"'.str_replace('"', '""', preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v).'"';
        $filas = [array_map($celda, ['Decreto', 'Proveedor', 'RUT', 'Factura', 'Fecha factura', 'Monto', 'Egresado', 'Saldo', 'Días', 'Referencia de los días', 'Plazo superado'])];
        foreach ($this->porEgresar() as $f) {
            $d = $f['decreto'];
            $filas[] = array_map($celda, [$d->folio(), $d->proveedor_nombre, $d->proveedor_run, $d->factura, $d->factura_fecha?->format('Y-m-d'), $d->monto, $d->egresado(), $f['saldo'], $f['dias'], $f['base'], $f['vencido'] ? 'Sí' : 'No']);
        }

        return "\xEF\xBB\xBF".implode("\r\n", array_map(fn ($r) => implode(';', $r), $filas))."\r\n";
    }
}
