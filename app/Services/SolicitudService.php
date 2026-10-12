<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Devolucion;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\Firma\ArchivoFirmaService;
use App\Support\UploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SolicitudService
{
    public function __construct(private readonly FileVault $vault, private readonly PdfService $pdf, private readonly AuditoriaService $audit, private readonly ArchivoFirmaService $firmas) {}

    /**
     * @param  array{run:string,nombres:string,apellidos:string}  $ciudadano  (de ClaveÚnica, no editable)
     * @param  array<int,UploadedFile>  $anexosRequeridos  indexados por id de TramiteAnexo
     * @param  array<int,UploadedFile>  $extras
     */
    public function ingresar(array $ciudadano, Tramite $tramite, string $correo, string $detalle, string $canal, array $anexosRequeridos, array $extras): Solicitud
    {
        if (! $tramite->activo) {
            throw new FlujoException('El trámite no está disponible.');
        }
        foreach ($tramite->anexos as $a) {
            if ($a->obligatorio && empty($anexosRequeridos[$a->id])) {
                throw new FlujoException('Falta el anexo obligatorio: '.$a->nombre);
            }
        }
        $archivos = [];
        foreach ($tramite->anexos as $a) {
            if (! empty($anexosRequeridos[$a->id])) {
                $archivos[] = [$anexosRequeridos[$a->id], $a->nombre];
            }
        }
        foreach ($extras as $f) {
            $archivos[] = [$f, 'Anexo adicional'];
        }
        if (count($archivos) > config('sgd.upload_max_files')) {
            throw new FlujoException('Máximo '.config('sgd.upload_max_files').' archivos por solicitud.');
        }
        $validados = [];
        foreach ($archivos as [$f, $titulo]) {
            try {
                $validados[] = [$f, $titulo, UploadValidator::validar($f)];
            } catch (\InvalidArgumentException $e) {
                throw new FlujoException($f->getClientOriginalName().': '.$e->getMessage());
            }
        }

        $creados = [];
        try {
            return DB::transaction(function () use ($ciudadano, $tramite, $correo, $detalle, $canal, $validados, &$creados) {
                $s = Solicitud::query()->create([
                    'folio' => $this->nuevoFolio(), 'run' => $ciudadano['run'], 'nombres' => $ciudadano['nombres'], 'apellidos' => $ciudadano['apellidos'],
                    'correo' => $correo, 'tramite_id' => $tramite->id, 'detalle' => $detalle, 'canal_notificacion' => $canal, 'estado' => 'ingresada',
                ]);
                $info = [];
                foreach ($validados as [$f, $titulo, $tipo]) {
                    $info[] = $this->guardarAnexo($s, $f, $titulo, $tipo, 'anexo', $ciudadano['run'], $creados);
                }
                $codigo = self::codigoComprobante($s->folio, array_column($info, 'sha256'));
                $pdf = $this->pdf->comprobante($s, $info, $codigo);
                $f = $this->vault->put($pdf);
                $creados[] = $f['ruta'];
                Archivo::query()->create([
                    'solicitud_id' => $s->id, 'ambito' => 'comprobante', 'nombre_original' => 'comprobante-'.$s->folio.'.pdf', 'mime' => 'application/pdf',
                    'tamano' => $f['tamano'], 'sha256' => $f['sha256'], 'ruta' => $f['ruta'], 'titulo' => 'Comprobante electrónico de ingreso',
                    'subido_por' => $ciudadano['run'], 'nivel_acceso' => 'reservado',
                ]);
                $this->audit->registrar('solicitud.ingreso', 'solicitud', $s->folio, ['tramite' => $tramite->codigo_interno, 'anexos' => count($info)], false, ['run' => $ciudadano['run'], 'rol' => 'ciudadano']);

                return $s;
            });
        } catch (\Throwable $e) {
            foreach ($creados as $r) {
                $this->vault->delete($r);
            }
            throw $e;
        }
    }

    /** @param array<int,string> $creados */
    private function guardarAnexo(Solicitud $s, UploadedFile $f, string $titulo, array $tipo, string $ambito, string $run, array &$creados): array
    {
        $bytes = (string) file_get_contents($f->getRealPath());
        $r = $this->vault->put($bytes);
        $creados[] = $r['ruta'];
        $nombre = preg_replace('/[^\w.\- ]+/u', '_', basename($f->getClientOriginalName())) ?: 'archivo';
        $a = new Archivo([
            'solicitud_id' => $s->id, 'expediente_id' => $s->expediente_id, 'ambito' => $ambito, 'nombre_original' => mb_substr($nombre, 0, 190), 'mime' => $tipo['mime'],
            'tamano' => $r['tamano'], 'sha256' => $r['sha256'], 'ruta' => $r['ruta'], 'titulo' => $titulo, 'subido_por' => $run, 'nivel_acceso' => 'reservado',
        ]);
        $this->firmas->analizar($a, $bytes);
        $a->save();

        return ['nombre' => $nombre, 'sha256' => $r['sha256'], 'tamano' => $r['tamano']];
    }

    public static function codigoComprobante(string $folio, array $hashes): string
    {
        sort($hashes);
        $h = hash_hmac('sha256', $folio.'|'.implode(',', $hashes), (string) config('app.key'));
        $alf = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alf[hexdec(substr($h, $i * 2, 2)) % 32];
        }

        return 'CMP-'.implode('-', str_split($out, 4));
    }

    private function nuevoFolio(): string
    {
        $alf = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $r = '';
            for ($i = 0; $i < 6; $i++) {
                $r .= $alf[random_int(0, 31)];
            }
            $folio = 'SD-'.now('America/Santiago')->year.'-'.$r;
        } while (Solicitud::query()->where('folio', $folio)->exists());

        return $folio;
    }

    public function devolver(Solicitud $s, Usuario $u, string $observacion, int $dias): Devolucion
    {
        if ($dias < 1 || $dias > 90) {
            throw new FlujoException('El plazo debe estar entre 1 y 90 días.');
        }

        return DB::transaction(function () use ($s, $u, $observacion, $dias) {
            $s = Solicitud::query()->lockForUpdate()->findOrFail($s->id);
            if ($s->estado !== 'en_tramite') {
                throw new FlujoException('La solicitud no está en trámite.');
            }
            $d = Devolucion::query()->create(['solicitud_id' => $s->id, 'creado_por' => $u->id, 'observacion' => $observacion, 'plazo_dias' => $dias, 'vence_en' => now('America/Santiago')->addDays($dias)->endOfDay()]);
            $s->forceFill(['estado' => 'devuelta'])->save();
            $this->audit->registrar('solicitud.devolver', 'solicitud', $s->folio, ['dias' => $dias]);

            return $d;
        });
    }

    /** @param array<int,UploadedFile> $archivos */
    public function responder(Solicitud $s, string $respuesta, array $archivos): void
    {
        $creados = [];
        $validados = [];
        foreach ($archivos as $f) {
            try {
                $validados[] = [$f, UploadValidator::validar($f)];
            } catch (\InvalidArgumentException $e) {
                throw new FlujoException($f->getClientOriginalName().': '.$e->getMessage());
            }
        }
        if (count($validados) > config('sgd.upload_max_files')) {
            throw new FlujoException('Demasiados archivos.');
        }
        try {
            DB::transaction(function () use ($s, $respuesta, $validados, &$creados) {
                $s = Solicitud::query()->lockForUpdate()->findOrFail($s->id);
                $dev = $s->devolucionPendiente();
                if ($s->estado !== 'devuelta' || ! $dev) {
                    throw new FlujoException('La solicitud no tiene una devolución pendiente.');
                }
                if ($dev->vence_en->isPast()) {
                    throw new FlujoException('El plazo para responder esta devolución venció.');
                }
                foreach ($validados as [$f, $tipo]) {
                    $this->guardarAnexo($s, $f, 'Respuesta del ciudadano', $tipo, 'respuesta', $s->run, $creados);
                }
                $dev->forceFill(['respuesta' => $respuesta, 'respondida_en' => now()])->save();
                $s->forceFill(['estado' => 'en_tramite'])->save();
                $this->audit->registrar('solicitud.responder', 'solicitud', $s->folio, [], false, ['run' => $s->run, 'rol' => 'ciudadano']);
            });
        } catch (\Throwable $e) {
            foreach ($creados as $r) {
                $this->vault->delete($r);
            }
            throw $e;
        }
    }
}
