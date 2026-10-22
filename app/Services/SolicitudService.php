<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Archivo;
use App\Models\Devolucion;
use App\Models\Solicitud;
use App\Models\Tramite;
use App\Models\Usuario;
use App\Services\Admin\ArchivoConfigService;
use App\Services\Firma\ArchivoFirmaService;
use App\Support\Plazos;
use App\Support\Run;
use App\Support\UploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SolicitudService
{
    public function __construct(private readonly FileVault $vault, private readonly PdfService $pdf, private readonly AuditoriaService $audit, private readonly ArchivoFirmaService $firmas, private readonly MovimientoService $mov) {}

    /**
     * Datos de la persona a cuyo nombre se presenta la solicitud (persona asistida). La declaración es de quien presenta; la acreditación (poder, parentesco,
     * tutela) la revisa el funcionario(a): el sistema no la verifica.
     *
     * @param  array<string,mixed>|null  $t
     * @return array{interesado_run:string,interesado_nombre:string,interesado_correo:?string,relacion:string,acreditacion:string}|null
     *
     * @throws FlujoException
     */
    private function validarTercero(array $ciudadano, ?array $t): ?array
    {
        if ($t === null) {
            return null;
        }
        $run = Run::normalizar((string) ($t['run'] ?? ''));
        if (! $run) {
            throw new FlujoException('El RUN de la persona a cuyo nombre presenta la solicitud no es válido.');
        }
        if ($run === Run::normalizar((string) $ciudadano['run'])) {
            throw new FlujoException('El RUN de la persona asistida no puede ser el suyo: si presenta la solicitud para usted, no marque esa opción.');
        }
        $nombre = trim((string) ($t['nombre'] ?? ''));
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 190) {
            throw new FlujoException('Indique el nombre completo de la persona a cuyo nombre presenta la solicitud.');
        }
        if (! isset(Solicitud::RELACIONES[(string) ($t['relacion'] ?? '')])) {
            throw new FlujoException('Indique su relación con esa persona.');
        }
        $acre = trim((string) ($t['acreditacion'] ?? ''));
        if (mb_strlen($acre) < 5 || mb_strlen($acre) > 500) {
            throw new FlujoException('Indique cómo acredita su representación o relación (poder, parentesco, tutela u otro antecedente; 5 a 500 caracteres).');
        }
        $correo = trim((string) ($t['correo'] ?? ''));
        if ($correo !== '' && (! filter_var($correo, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $correo) || mb_strlen($correo) > 190)) {
            throw new FlujoException('El correo de la persona asistida no es válido.');
        }
        if (empty($t['declara'])) {
            throw new FlujoException('Debe declarar que actúa con el conocimiento de esa persona o en su representación.');
        }

        return ['interesado_run' => $run, 'interesado_nombre' => $nombre, 'interesado_correo' => $correo !== '' ? $correo : null, 'relacion' => (string) $t['relacion'], 'acreditacion' => $acre];
    }

    /**
     * @param  array{run:string,nombres:string,apellidos:string}  $ciudadano  (de ClaveÚnica, no editable)
     * @param  array<int,UploadedFile>  $anexosRequeridos  indexados por id de TramiteAnexo
     * @param  array<int,UploadedFile>  $extras
     * @param  array{run:string,nombre:string,correo?:?string,relacion:string,acreditacion:string,declara:mixed}|null  $tercero  persona a cuyo nombre se presenta (persona asistida)
     */
    public function ingresar(array $ciudadano, Tramite $tramite, string $correo, string $detalle, string $canal, array $anexosRequeridos, array $extras, ?bool $consiente = null, ?string $consentimientoVersion = null, ?array $tercero = null): Solicitud
    {
        $tercero = $this->validarTercero($ciudadano, $tercero);
        if (! $tramite->estaPublicado()) {
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
        if (count($archivos) > ArchivoConfigService::maxArchivos()) {
            throw new FlujoException('Máximo '.ArchivoConfigService::maxArchivos().' archivos por solicitud.');
        }
        $validados = [];
        foreach ($archivos as [$f, $titulo]) {
            try {
                $validados[] = [$f, $titulo, UploadValidator::validar($f, 'ciudadano')];
            } catch (\InvalidArgumentException $e) {
                throw new FlujoException($f->getClientOriginalName().': '.$e->getMessage());
            }
        }

        $creados = [];
        try {
            return DB::transaction(function () use ($ciudadano, $tramite, $correo, $detalle, $canal, $validados, $consiente, $consentimientoVersion, $tercero, &$creados) {
                $s = Solicitud::query()->create([
                    'folio' => $this->nuevoFolio(), 'run' => $ciudadano['run'], 'nombres' => $ciudadano['nombres'], 'apellidos' => $ciudadano['apellidos'],
                    'correo' => $correo, 'tramite_id' => $tramite->id, 'detalle' => $detalle, 'canal_notificacion' => $canal, 'estado' => 'ingresada',
                    'consentimiento_datos' => $consiente, 'consentimiento_en' => $consiente !== null ? now() : null, 'consentimiento_version' => $consiente !== null ? $consentimientoVersion : null,
                ] + ($tercero ? ['en_nombre_de' => true, 'declaracion_en' => now()] + $tercero : []));
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
                $this->audit->registrar('solicitud.ingreso', 'solicitud', $s->folio, ['tramite' => $tramite->codigo_interno, 'anexos' => count($info), 'consentimiento' => $consiente === null ? null : ($consiente ? 'si' : 'no')], false, ['run' => $ciudadano['run'], 'rol' => 'ciudadano']);
                $this->mov->registrar($s, 'ingreso', 'Su solicitud fue ingresada. Quedó a cargo de la dirección municipal responsable del trámite.');

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
            $d = Devolucion::query()->create(['solicitud_id' => $s->id, 'creado_por' => $u->id, 'observacion' => $observacion, 'plazo_dias' => $dias, 'vence_en' => Plazos::vencimiento(now('America/Santiago'), $dias, true)]);
            $s->forceFill(['estado' => 'devuelta'])->save();
            $this->audit->registrar('solicitud.devolver', 'solicitud', $s->folio, ['dias' => $dias]);
            $this->mov->registrar($s, 'devuelta', 'Su solicitud fue devuelta con observaciones: tiene '.$dias.' días para responder desde su sección «Mis solicitudes».');

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
                $validados[] = [$f, UploadValidator::validar($f, 'ciudadano')];
            } catch (\InvalidArgumentException $e) {
                throw new FlujoException($f->getClientOriginalName().': '.$e->getMessage());
            }
        }
        if (count($validados) > ArchivoConfigService::maxArchivos()) {
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
                $this->mov->registrar($s, 'respondida', 'Recibimos su respuesta a las observaciones; su solicitud continúa en trámite.');
            });
        } catch (\Throwable $e) {
            foreach ($creados as $r) {
                $this->vault->delete($r);
            }
            throw $e;
        }
    }
}
