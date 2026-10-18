<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\DecretoPago;
use App\Models\Expediente;
use App\Models\Plantilla;
use App\Models\Usuario;
use App\Services\Firma\ArchivoFirmaService;
use App\Support\MontoEnLetras;
use App\Support\Run;
use App\Support\UploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Decreto de pago: se asocia el expediente de pago y el proceso de compra, y el PDF del decreto se GENERA con una plantilla (con el flujo de
 * visación y firma configurado y su correlativo propio) o se CARGA ya firmado. Luego Tesorería emite el egreso (EgresoService).
 */
class DecretoPagoService
{
    public const MONTO_MAX = 99_999_999_999;

    public function __construct(
        private readonly AuditoriaService $audit, private readonly DocumentoService $docs, private readonly PagoConfigService $config,
        private readonly ExpedienteVinculoService $vinculos, private readonly FileVault $vault, private readonly ArchivoFirmaService $firmas,
    ) {}

    private function exigirGestion(Usuario $u, DecretoPago $d): void
    {
        if (! $u->tienePermiso('decretopago.gestionar') || ! $u->can('operar', $d->expediente)) {
            throw new FlujoException('No tiene permiso para gestionar este decreto de pago.');
        }
        if ($d->estado === 'anulado') {
            throw new FlujoException('El decreto de pago está anulado.');
        }
    }

    /** @param array<string,mixed> $x @return array<string,mixed> */
    private function validar(array $x): array
    {
        $t = fn (string $k, int $min, int $max, string $et) => (function () use ($x, $k, $min, $max, $et) {
            $v = mb_substr(TramiteService::limpiarTexto((string) ($x[$k] ?? '')), 0, $max);
            if (mb_strlen($v) < $min) {
                throw new FlujoException("$et: mínimo $min caracteres.");
            }

            return $v;
        })();
        $monto = $x['monto'] ?? null;
        if (! is_numeric($monto) || (int) $monto < 1 || (int) $monto > self::MONTO_MAX || (string) (int) $monto !== (string) $monto) {
            throw new FlujoException('El monto debe ser un número entero de pesos mayor que cero.');
        }
        $run = null;
        if (! empty($x['proveedor_run'])) {
            $run = Run::normalizar((string) $x['proveedor_run']) ?? throw new FlujoException('El RUT del proveedor no es válido.');
        }
        $opc = fn (string $k, int $max) => ($v = mb_substr(TramiteService::limpiarTexto((string) ($x[$k] ?? '')), 0, $max)) !== '' ? $v : null;
        $fecha = null;
        if (! empty($x['fecha'])) {
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', (string) $x['fecha'])->format('Y-m-d');
                if ($fecha !== (string) $x['fecha']) {
                    throw new \InvalidArgumentException('fecha');
                }
            } catch (\Throwable) {
                throw new FlujoException('Fecha inválida.');
            }
        }

        return ['proveedor_nombre' => $t('proveedor_nombre', 3, 160, 'El nombre del proveedor'), 'proveedor_run' => $run, 'concepto' => $t('concepto', 10, 500, 'El concepto del pago'), 'monto' => (int) $monto,
            'imputacion' => $opc('imputacion', 160), 'orden_compra' => $opc('orden_compra', 60), 'factura' => $opc('factura', 60), 'fecha' => $fecha];
    }

    /** @param array<string,mixed> $x */
    public function crear(Usuario $u, Expediente $pago, ?Expediente $compra, array $x): DecretoPago
    {
        if (! $u->tienePermiso('decretopago.gestionar') || ! $u->can('operar', $pago)) {
            throw new FlujoException('No tiene permiso para crear un decreto de pago en este expediente.');
        }
        if ($pago->estado !== 'abierto') {
            throw new FlujoException('El expediente de pago está cerrado.');
        }
        if (DecretoPago::query()->where('expediente_id', $pago->id)->where('estado', 'abierto')->exists()) {
            throw new FlujoException('Este expediente de pago ya tiene un decreto de pago.');
        }
        if ($compra && ($compra->id === $pago->id || ! $u->can('ver', $compra))) {
            throw new FlujoException('El expediente del proceso de compra no existe o no puede asociarlo.');
        }
        $datos = $this->validar($x);

        return DB::transaction(function () use ($u, $pago, $compra, $datos) {
            $d = DecretoPago::query()->create($datos + ['expediente_id' => $pago->id, 'compra_expediente_id' => $compra?->id, 'estado' => 'abierto', 'creado_por' => $u->id]);
            if ($compra && $u->tienePermiso('expedientes.vincular') && ! $pago->vinculos()->where('tipo', 'expediente')->where('origen_expediente_id', $compra->id)->exists()) {
                $this->vinculos->vincularExpediente($u, $pago, $compra->id, 'Proceso de compra del decreto de pago de '.$datos['proveedor_nombre']);
            }
            $this->audit->registrar('decretopago.crear', 'decreto_pago', $d->id, ['expediente' => $pago->id, 'compra' => $compra?->id, 'monto' => $d->monto]);

            return $d;
        });
    }

    /** @param array<string,mixed> $x */
    public function actualizar(Usuario $u, DecretoPago $d, array $x): DecretoPago
    {
        $this->exigirGestion($u, $d);
        if ($d->fase() !== 'borrador' || $d->documento_id || $d->archivo_id) {
            throw new FlujoException('Solo se editan los datos mientras no se genere ni cargue el decreto.');
        }
        $d->forceFill($this->validar($x))->save();
        $this->audit->registrar('decretopago.editar', 'decreto_pago', $d->id);

        return $d;
    }

    /** Campos de combinación para la plantilla del decreto de pago. @return array<string,string> */
    public function variables(DecretoPago $d): array
    {
        $d->loadMissing('compra');
        $nada = '—';

        return ['proveedor_nombre' => $d->proveedor_nombre, 'proveedor_run' => $d->proveedor_run ?: $nada, 'concepto' => $d->concepto, 'monto' => '$'.number_format($d->monto, 0, ',', '.'),
            'monto_letras' => MontoEnLetras::pesos($d->monto), 'imputacion' => $d->imputacion ?: $nada, 'orden_compra' => $d->orden_compra ?: $nada, 'factura' => $d->factura ?: $nada,
            'expediente_compra' => $d->compra?->codigo ?: $nada, 'fecha_pago' => $d->fecha?->format('d-m-Y') ?: $nada];
    }

    /** Genera el decreto (borrador editable) con la plantilla del decreto de pago. */
    public function generar(Usuario $u, DecretoPago $d, Plantilla $plantilla): DecretoPago
    {
        $this->exigirGestion($u, $d);
        $tipo = $this->config->tipo('decreto_pago');
        if ($d->documento_id || $d->archivo_id) {
            throw new FlujoException('Este decreto ya tiene un documento generado o cargado.');
        }
        if ($plantilla->tipo_documento_id !== $tipo->id || ! $plantilla->activo) {
            throw new FlujoException('Elija una plantilla activa del tipo «'.$tipo->nombre.'».');
        }
        $titulo = 'Decreto de pago — '.$d->proveedor_nombre;
        try {
            $doc = $this->docs->crear($d->expediente, $tipo, $plantilla, mb_substr($titulo, 0, 190), $u, $this->variables($d));
        } catch (FlujoException $e) {
            throw new FlujoException($e->getMessage());
        }
        $d->forceFill(['documento_id' => $doc->id, 'origen' => 'generado'])->save();
        $this->audit->registrar('decretopago.generar', 'decreto_pago', $d->id, ['documento' => $doc->id, 'plantilla' => $plantilla->id]);

        return $d;
    }

    /** Envía el borrador a visación y firma con los firmantes configurados del decreto de pago. */
    public function enviarAFirma(Usuario $u, DecretoPago $d): DecretoPago
    {
        $this->exigirGestion($u, $d);
        $doc = $d->documento ?? throw new FlujoException('Primero genere el decreto de pago.');
        if ($doc->estado !== 'borrador') {
            throw new FlujoException('El decreto ya está en visación y firma o ya fue firmado.');
        }
        $f = $this->config->flujo('decreto_pago');
        $this->docs->definirFlujo($doc, $f['flujo'], $f['visadores'], $f['firmantes'], $f['permitidas']);
        $this->audit->registrar('decretopago.enviar_firma', 'decreto_pago', $d->id, ['flujo' => $f['flujo']]);

        return $d->refresh();
    }

    /** Carga el PDF de un decreto de pago ya emitido (por ejemplo, firmado fuera del sistema). */
    public function cargarPdf(Usuario $u, DecretoPago $d, UploadedFile $pdf, string $numero, string $fechaDecreto): DecretoPago
    {
        $this->exigirGestion($u, $d);
        if ($d->documento_id || $d->archivo_id) {
            throw new FlujoException('Este decreto ya tiene un documento generado o cargado.');
        }
        $numero = mb_substr(TramiteService::limpiarTexto($numero), 0, 40);
        if (mb_strlen($numero) < 1) {
            throw new FlujoException('Indique el número del decreto de pago.');
        }
        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $fechaDecreto)->format('Y-m-d');
            if ($fecha !== $fechaDecreto) {
                throw new FlujoException('Fecha del decreto inválida.');
            }
            $tipo = UploadValidator::validar($pdf);
        } catch (FlujoException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw new FlujoException($e->getMessage());
        } catch (\Throwable) {
            throw new FlujoException('Fecha del decreto inválida.');
        }
        if ($tipo['mime'] !== 'application/pdf') {
            throw new FlujoException('Cargue el decreto en formato PDF.');
        }
        $bytes = (string) file_get_contents($pdf->getRealPath());
        $f = $this->vault->put($bytes);
        try {
            DB::transaction(function () use ($u, $d, $bytes, $f, $numero, $fecha) {
                $a = new Archivo(['expediente_id' => $d->expediente_id, 'ambito' => 'expediente', 'nombre_original' => 'decreto-de-pago-'.preg_replace('/[^\w\-]+/u', '_', $numero).'.pdf', 'mime' => 'application/pdf', 'tamano' => $f['tamano'],
                    'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => 'Decreto de pago N° '.$numero.' (cargado)', 'nivel_acceso' => 'reservado', 'sensible' => false, 'subido_por' => $u->run]);
                $this->firmas->analizar($a, $bytes);
                $a->save();
                $d->forceFill(['archivo_id' => $a->id, 'origen' => 'cargado', 'numero_externo' => $numero, 'fecha' => $fecha])->save();
                $this->audit->registrar('decretopago.cargar', 'decreto_pago', $d->id, ['archivo' => $a->id, 'sha256' => $f['sha256']]);
            });
        } catch (\Throwable $e) {
            $this->vault->delete($f['ruta']);
            throw $e;
        }

        return $d->refresh();
    }

    public function anular(Usuario $u, DecretoPago $d, string $motivo): void
    {
        $this->exigirGestion($u, $d);
        $motivo = mb_substr(TramiteService::limpiarTexto($motivo), 0, 300);
        if (mb_strlen($motivo) < 5) {
            throw new FlujoException('Indique el motivo de la anulación.');
        }
        $d->load('egresos', 'documento');
        if ($d->egresos->where('estado', 'abierto')->isNotEmpty()) {
            throw new FlujoException('Tiene egresos en curso o emitidos: anúlelos primero (un egreso emitido no se anula aquí).');
        }
        $doc = $d->documento;
        if ($doc && $doc->estado === 'vigente') {
            throw new FlujoException('Un decreto firmado y vigente no se anula aquí: dicte un decreto que lo deje sin efecto.');
        }
        DB::transaction(function () use ($d, $doc, $motivo) {
            if ($doc && $doc->estado === 'en_flujo') {
                $doc->pasos()->delete();
                $doc->forceFill(['estado' => 'borrador'])->save(); // vuelve a edición: los firmantes dejan de verlo en su bandeja
            }
            $d->forceFill(['estado' => 'anulado', 'anulado_motivo' => $motivo])->save();
            $this->audit->registrar('decretopago.anular', 'decreto_pago', $d->id);
        });
    }
}
