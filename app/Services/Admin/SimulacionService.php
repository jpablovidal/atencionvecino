<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Expediente;
use App\Models\Plantilla;
use App\Models\Solicitud;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\ArchivoVersionService;
use App\Services\DecretoPagoService;
use App\Services\DocumentoService;
use App\Services\EgresoService;
use App\Services\ExpedienteService;
use App\Services\RespuestaFinalService;
use App\Services\SolicitudService;
use App\Support\Ambiente;
use App\Support\Run;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Procesos SIMULADOS para ver cómo funciona el sistema: solicitudes de vecinos ficticios, expedientes en distintos estados, documentos con
 * varias versiones, visación y firma, archivos versionados, respuestas finales, un decreto de pago con su egreso y una devolución.
 * Usa los MISMOS servicios que la operación real (nada se inserta "a mano"). Solo en ambiente de pruebas; todo se borra al pasar a producción.
 */
class SimulacionService
{
    public const MARCA = '[SIMULADO]';

    public function __construct(
        private readonly SolicitudService $solicitudes,
        private readonly ExpedienteService $expedientes,
        private readonly DocumentoService $docs,
        private readonly ArchivoVersionService $versiones,
        private readonly RespuestaFinalService $respuestas,
        private readonly DecretoPagoService $decretos,
        private readonly EgresoService $egresos,
    ) {}

    public static function yaCargada(): bool
    {
        return Solicitud::query()->where('detalle', 'like', self::MARCA.'%')->exists();
    }

    /** @return list<string> líneas de resultado para mostrar */
    public function cargar(): array
    {
        if (! Ambiente::esPruebas()) {
            throw new FlujoException('La simulación solo existe en el ambiente de pruebas.');
        }
        if (self::yaCargada()) {
            throw new FlujoException('Ya hay procesos simulados cargados. Para empezar de nuevo use «Borrar datos simulados» o pase a producción.');
        }
        $u = fn (string $rol) => Usuario::query()->where('rol', $rol)->where('activo', true)->orderBy('id')->first();
        $dom = Usuario::query()->where('rol', 'funcionario')->where('activo', true)->where('unidad_id', 3)->orderBy('id')->first() ?? $u('funcionario');
        $sec = $u('secretario');
        $alc = $u('alcalde');
        $partes = $u('oficina_partes');
        $conta = $u('contabilidad');
        $tes = $u('tesorero');
        if (! $dom || ! $sec || ! $alc) {
            throw new FlujoException('Faltan usuarios de prueba (funcionario de la DOM, secretario y alcalde). Cargue el sembrado de demostración.');
        }
        $tramite = Tramite::query()->with('anexos')->where('estado', 'publicado')->where('es_transparencia', false)->orderBy('id')->first() ?? Tramite::query()->with('anexos')->orderBy('id')->first();
        if (! $tramite) {
            throw new FlujoException('No hay trámites publicados: cargue el sembrado de demostración.');
        }
        $log = [];
        $n = 0;
        $nueva = function (string $nombre, string $detalle) use (&$n, $tramite): Solicitud {
            $n++;
            $run = (string) (20000100 + $n);

            return $this->solicitudes->ingresar(['run' => $run.'-'.Run::dv($run), 'nombres' => $nombre, 'apellidos' => 'Simulado'], $tramite, 'vecino'.$n.'@example.invalid', self::MARCA.' '.$detalle, 'correo',
                $this->anexos($tramite, $n), []);
        };
        $paso = function (string $titulo, callable $f) use (&$log): void {
            try {
                $log[] = '✔ '.$titulo.' — '.$f();
            } catch (\Throwable $e) {
                $log[] = '✖ '.$titulo.' — '.$e->getMessage();
            }
        };

        // 1. Solicitud recién ingresada (sin expediente): se ve en la bandeja de la unidad
        $paso('Solicitud ingresada, esperando que alguien la tome', fn () => 'folio '.$nueva('Marta', 'Solicito certificado de número para mi propiedad en el sector Mirasol.')->folio);

        // 2. Expediente abierto con documento en edición (varias versiones)
        $paso('Expediente abierto con un oficio en 3 versiones', function () use ($nueva, $dom) {
            $e = $this->expedientes->crearDesdeSolicitud($nueva('Jorge', 'Solicito autorización para instalar un cierre perimetral en mi parcela.'), $dom);
            $d = $this->docs->crear($e, TipoDocumento::query()->where('codigo', 'OFICIO')->firstOrFail(), null, 'Oficio de respuesta (borrador)', $dom, [], '<p>Se acusa recibo de su solicitud.</p>');
            $d = $this->docs->guardar($d, $d->titulo, '<p>Se acusa recibo de su solicitud.</p><p>Se solicitan antecedentes adicionales: certificado de dominio vigente.</p>', [], $dom);
            $this->docs->guardar($d->refresh(), $d->titulo, '<p>Se acusa recibo de su solicitud.</p><p>Se solicitan antecedentes adicionales: certificado de dominio vigente y plano de ubicación.</p>', [], $dom);

            return $e->codigo.' ('.$this->expedientes->identificador($e).')';
        });

        // 3. Documento visado y firmado (vigente)
        $paso('Oficio visado por Secretaría y firmado por Alcaldía', function () use ($nueva, $dom, $sec, $alc) {
            $e = $this->expedientes->crearDesdeSolicitud($nueva('Lorena', 'Solicito patente provisoria para un puesto de venta de flores.'), $dom);
            $d = $this->docs->crear($e, TipoDocumento::query()->where('codigo', 'OFICIO')->firstOrFail(), null, 'Oficio conductor', $dom, [], '<p>Se remite a usted el pronunciamiento de la Dirección.</p>');
            $this->docs->definirFlujo($d, 'agil', [$sec->id], [$alc->id]);
            $this->docs->visar($d->refresh(), $sec, true, 'Conforme');
            $d = $this->docs->firmar($d->refresh(), $alc, '111222');

            return $d->folio ?? $d->numeroFormateado() ?? 'firmado';
        });

        // 4. Archivo con versiones y un archivo restringido
        $paso('Archivo del expediente con 3 versiones', function () use ($nueva, $dom) {
            $e = $this->expedientes->crearDesdeSolicitud($nueva('Pedro', 'Solicito certificado de recepción final de obras.'), $dom);
            $a = Archivo::query()->where('expediente_id', $e->id)->where('ambito', 'anexo')->firstOrFail();
            $this->versiones->nuevaVersion($a, $this->pdf('plano-v2.pdf', 'Plano corregido por observaciones de la DOM'), $dom, 'Se corrigió la superficie construida según observación de la DOM');
            $this->versiones->nuevaVersion($a->refresh(), $this->pdf('plano-v3.pdf', 'Plano con firma del arquitecto'), $dom, 'Se agregó la firma del arquitecto patrocinante');

            return $e->codigo.' · archivo «'.$a->nombre_original.'» ahora en v3';
        });

        // 5. Respuesta final despachada y expediente cerrado
        if ($partes) {
            $paso('Respuesta final notificada por Casilla Única y expediente cerrado', function () use ($nueva, $dom, $sec, $alc, $partes) {
                $e = $this->expedientes->crearDesdeSolicitud($nueva('Carolina', 'Solicito copia autorizada de mi permiso de edificación.'), $dom);
                $d = $this->docs->crear($e, TipoDocumento::query()->where('codigo', 'OFICIO')->firstOrFail(), null, 'Oficio de respuesta', $dom, [], '<p>Se adjunta copia autorizada del permiso.</p>');
                $this->docs->definirFlujo($d, 'agil', [$sec->id], [$alc->id]);
                $this->docs->visar($d->refresh(), $sec, true, null);
                $d = $this->docs->firmar($d->refresh(), $alc, '111222');
                $a = Archivo::query()->where('expediente_id', $e->id)->where('ambito', 'anexo')->firstOrFail();
                $r = $this->respuestas->instruir($dom, $e->refresh(), [$d->id], [$a->id], 'casilla_unica', null, 'Entregar el oficio y el plano', true);
                $this->expedientes->cerrar($e->refresh());
                try {
                    $this->respuestas->despachar($partes, $r->refresh(), null);
                    $estado = 'notificada';
                } catch (\Throwable $x) {
                    $estado = 'instruida (el despacho real necesita Casilla Única y Gmail configurados: '.$x->getMessage().')';
                }

                return $e->codigo.' cerrado; respuesta '.$estado;
            });
        } else {
            $log[] = '• Respuesta final: omitida (falta el usuario de Oficina de Partes; ejecute php artisan pruebas:partes).';
        }

        // 6. Solicitud devuelta al ciudadano por falta de antecedentes
        $paso('Solicitud devuelta al vecino por falta de antecedentes', function () use ($nueva, $dom) {
            $s = $nueva('Rodrigo', 'Solicito permiso de ampliación de vivienda.');
            $this->expedientes->crearDesdeSolicitud($s, $dom);
            $this->solicitudes->devolver($s->refresh(), $dom, 'Falta adjuntar el certificado de informaciones previas.', 10);

            return 'folio '.$s->folio;
        });

        // 7. Decreto de pago + egreso
        if ($conta && $tes) {
            $paso('Decreto de pago firmado y egreso de Tesorería', function () use ($nueva, $conta, $tes, $sec, $alc, $tramite) {
                DB::table('tramite_unidad')->insertOrIgnore(['tramite_id' => $tramite->id, 'unidad_id' => $conta->unidad_id]);
                $pago = $this->expedientes->crearDesdeSolicitud($nueva('Proveedor', 'Expediente de pago por mantención de alumbrado público.'), $conta);
                $compra = $this->expedientes->crearDesdeSolicitud($nueva('Compras', 'Proceso de compra: licitación de mantención de alumbrado.'), $conta);
                $d = $this->decretos->crear($conta, $pago, $compra, ['proveedor_nombre' => 'Luminarias del Sur SpA', 'proveedor_run' => '76555444-'.Run::dv('76555444'), 'concepto' => 'Pago de la factura 4581 por mantención de alumbrado público',
                    'monto' => '3480000', 'imputacion' => '215-22-06-004', 'orden_compra' => 'OC-2210-15-SE26', 'factura' => 'F-4581', 'fecha' => now('America/Santiago')->format('Y-m-d')]);
                $plantilla = Plantilla::query()->where('nombre', 'Decreto de pago (ejemplo)')->first();
                if (! $plantilla) {
                    throw new FlujoException('Falta la plantilla de ejemplo «Decreto de pago»: ejecute php artisan plantillas:ejemplos.');
                }
                $d = $this->decretos->generar($conta, $d, $plantilla);
                $this->decretos->enviarAFirma($conta, $d);
                $doc = $d->documento->refresh();
                $this->docs->visar($doc, $sec, true, null);
                $this->docs->firmar($doc->refresh(), $alc, '111222');
                $e = $this->egresos->crear($tes, $d->refresh(), ['fecha' => now('America/Santiago')->format('Y-m-d'), 'medio_pago' => 'transferencia', 'monto' => '3480000', 'detalle' => 'Transferencia al proveedor Luminarias del Sur SpA']);
                $this->docs->firmar($e->documento->refresh(), $tes, null, 'simple');

                return 'decreto $3.480.000 y egreso firmado';
            });
        } else {
            $log[] = '• Decreto de pago: omitido (faltan Tesorero o Encargado de pagos; ejecute php artisan pruebas:pagos).';
        }

        return $log;
    }

    /** @return array<int,UploadedFile> anexos requeridos del trámite, uno por cada tipo pedido */
    private function anexos(Tramite $t, int $n): array
    {
        $o = [];
        foreach ($t->anexos as $a) {
            $o[$a->id] = $this->pdf('anexo-'.$a->id.'.pdf', 'Anexo simulado '.$a->id.'-'.$n);
        }

        return $o;
    }

    private function pdf(string $nombre, string $texto): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'sim');
        file_put_contents($p, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n% ".$texto.' '.bin2hex(random_bytes(4))."\ntrailer<</Root 1 0 R>>\n%%EOF");

        return new UploadedFile($p, $nombre, 'application/pdf', null, true);
    }
}
