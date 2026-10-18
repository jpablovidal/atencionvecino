<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\ComunicacionOficial;
use App\Models\Documento;
use App\Models\DocumentoPaso;
use App\Models\DocumentoVersion;
use App\Models\Expediente;
use App\Models\Plantilla;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\Firma\FirmaClient;
use App\Services\Firma\FirmaException;
use App\Support\PlantillaEstructura;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DocumentoService
{
    public function __construct(
        private readonly HtmlSanitizer $san,
        private readonly PdfService $pdf,
        private readonly FileVault $vault,
        private readonly CorrelativoService $correlativos,
        private readonly DelegacionService $delegaciones,
        private readonly AuditoriaService $audit,
        private readonly FirmaClient $firma,
        private readonly FolioService $folios,
    ) {}

    // ---------------------------------------------------------------- borradores

    /**
     * @param  array<string,string>  $vars  campos de combinación adicionales (p. ej. los del decreto de pago)
     * @param  string|null  $htmlInicial  contenido ya compuesto por el sistema (sin plantilla)
     */
    public function crear(Expediente $exp, TipoDocumento $tipo, ?Plantilla $plantilla, string $titulo, Usuario $autor, array $vars = [], ?string $htmlInicial = null): Documento
    {
        $titulo = HtmlSanitizer::sinInvisibles($titulo);
        if ($exp->estado !== 'abierto') {
            throw new FlujoException('El expediente está cerrado.');
        }
        if ($exp->origen === 'docdigital' && ComunicacionOficial::query()->where('id', $exp->comunicacion_id)->value('acuse_estado') !== 'registrado') {
            throw new FlujoException('Debe acusar recibo de la comunicación recibida antes de gestionarla y responderla.');
        }
        $html = $htmlInicial ?? ($plantilla ? $this->combinar($plantilla->contenido_html, $exp, $autor, $plantilla, $vars) : '<p></p>');

        return DB::transaction(function () use ($exp, $tipo, $titulo, $autor, $html, $plantilla) {
            $doc = Documento::query()->create([
                'expediente_id' => $exp->id, 'tipo_documento_id' => $tipo->id, 'titulo' => $titulo, 'html' => '', 'creado_por' => $autor->id,
                'nivel_acceso' => 'reservado', 'sensible' => false, 'estado' => 'borrador', 'flujo' => 'agil', 'version_actual' => 0, 'plantilla_id' => $plantilla?->id,
            ]);
            $this->nuevaVersion($doc, $html, $autor);
            $this->audit->registrar('documento.crear', 'documento', $doc->id, ['tipo' => $tipo->codigo]);

            return $doc->refresh();
        });
    }

    /** Campos de combinación; los valores se ESCAPAN antes de insertarse y luego todo se sanitiza. */
    public function combinar(string $html, Expediente $exp, ?Usuario $autor = null, ?Plantilla $plantilla = null, array $extra = []): string
    {
        $exp->loadMissing(['solicitud.tramite', 'unidad']);
        $s = $exp->solicitud;
        $v = [
            'fecha' => now('America/Santiago')->format('d-m-Y'),
            'expediente' => $exp->codigo,
            'folio' => $s?->folio,
            'solicitante_nombre' => $s ? $s->nombres.' '.$s->apellidos : '',
            'solicitante_run' => $s?->run,
            'tramite' => $s?->tramite?->nombre,
            'unidad' => $exp->unidad->nombre,
            'institucion_origen' => $exp->metadatos['relaciones']['institucion_origen'] ?? '',
            'asunto_origen' => $exp->origen === 'docdigital' ? ($exp->metadatos['descripcion']['resumen'] ?? '') : '',
            'folio_docdigital' => $exp->metadatos['descripcion']['folio_docdigital'] ?? '',
        ] + $extra + PlantillaEstructura::valoresCadena($plantilla?->estructura, $autor);

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/', fn ($m) => array_key_exists($m[1], $v) ? e((string) $v[$m[1]]) : $m[0], $html) ?? $html;
    }

    public function guardar(Documento $doc, string $titulo, string $html, array $meta, Usuario $autor): Documento
    {
        $titulo = HtmlSanitizer::sinInvisibles($titulo);

        return DB::transaction(function () use ($doc, $titulo, $html, $meta, $autor) {
            $doc = Documento::query()->lockForUpdate()->findOrFail($doc->id);
            if (! $doc->editable()) {
                throw new FlujoException('El documento no es editable en su estado actual ('.$doc->estado.').');
            }
            $doc->fill(['titulo' => $titulo] + $meta)->save();
            $this->nuevaVersion($doc, $html, $autor);
            $this->audit->registrar('documento.guardar', 'documento', $doc->id, ['version' => $doc->version_actual]);

            return $doc->refresh();
        });
    }

    private function nuevaVersion(Documento $doc, string $htmlCrudo, Usuario $autor): DocumentoVersion
    {
        $html = $this->san->limpiar($htmlCrudo);
        $v = $doc->version_actual + 1;
        $pdf = $this->pdf->documento($doc->loadMissing('tipo'), $html, $v);
        $f = $this->vault->put($pdf);
        $ver = DocumentoVersion::query()->create([
            'documento_id' => $doc->id, 'version' => $v, 'html' => $html, 'html_sha256' => hash('sha256', $html),
            'pdf_ruta' => $f['ruta'], 'pdf_sha256' => $f['sha256'], 'creado_por' => $autor->id,
        ]);
        $doc->forceFill(['html' => $html, 'version_actual' => $v, 'pdf_ruta' => $f['ruta'], 'pdf_sha256' => $f['sha256']])->save();
        // un documento editado invalida cualquier flujo previo
        $doc->pasos()->delete();

        return $ver;
    }

    // ---------------------------------------------------------------- flujo

    /**
     * @param  array<int,int>  $visadores  ids en orden
     * @param  array<int,int>  $firmantes  ids en orden
     * @param  array<int,string>  $permitidas  usuario_id => avanzada|simple|ambas (por defecto avanzada). La firma simple exige un tipo de documento que la permita.
     */
    public function definirFlujo(Documento $doc, string $flujo, array $visadores, array $firmantes, array $permitidas = []): Documento
    {
        return DB::transaction(function () use ($doc, $flujo, $visadores, $firmantes, $permitidas) {
            $doc = Documento::query()->with('tipo')->lockForUpdate()->findOrFail($doc->id);
            if ($doc->estado !== 'borrador') {
                throw new FlujoException('Solo se puede definir el flujo de un borrador.');
            }
            if (! in_array($flujo, ['agil', 'clasico'], true)) {
                throw new FlujoException('Flujo inválido.');
            }
            $requeridas = $flujo === 'agil' ? $doc->tipo->firmas_agil : $doc->tipo->firmas_clasico;
            if (count($firmantes) !== $requeridas) {
                throw new FlujoException("El flujo $flujo de este tipo de documento exige exactamente $requeridas firma(s).");
            }
            $todos = array_merge($visadores, $firmantes);
            if (count($todos) !== count(array_unique($todos))) {
                throw new FlujoException('Una persona no puede visar y firmar el mismo documento, ni repetirse en el flujo.');
            }
            $usuarios = Usuario::query()->whereIn('id', $todos)->get()->keyBy('id');
            foreach ($todos as $id) {
                if (! isset($usuarios[$id]) || ! $usuarios[$id]->activo) {
                    throw new FlujoException('Participante inexistente o inactivo.');
                }
                if (! $usuarios[$id]->operaExpedientes()) {
                    throw new FlujoException('Solo funcionarios, alcalde o secretario pueden participar en el flujo.');
                }
            }
            foreach ($firmantes as $i => $id) {
                if (! $usuarios[$id]->puede_firmar) {
                    throw new FlujoException($usuarios[$id]->nombreCompleto().' no está habilitado(a) para firmar.');
                }
                if ($i === 0 && $doc->tipo->firma_alcalde && ! $this->delegaciones->puedeFirmarComoAlcalde($usuarios[$id], $doc->tipo)) {
                    throw new FlujoException('Este tipo de documento debe ser firmado por el alcalde o por quien tenga delegación vigente.');
                }
            }
            foreach ($firmantes as $id) {
                $perm = $permitidas[$id] ?? 'avanzada';
                if (! in_array($perm, ['avanzada', 'simple', 'ambas'], true)) {
                    throw new FlujoException('Tipo de firma inválido.');
                }
                if ($perm !== 'avanzada' && ! $doc->tipo->permite_firma_simple) {
                    throw new FlujoException('Este tipo de documento no admite firma electrónica simple: solo firma avanzada.');
                }
            }
            $doc->pasos()->delete();
            $orden = 1;
            foreach ($visadores as $id) {
                DocumentoPaso::query()->create(['documento_id' => $doc->id, 'orden' => $orden++, 'tipo' => 'visacion', 'usuario_id' => $id]);
            }
            foreach ($firmantes as $id) {
                DocumentoPaso::query()->create(['documento_id' => $doc->id, 'orden' => $orden++, 'tipo' => 'firma', 'usuario_id' => $id, 'firma_permitida' => $permitidas[$id] ?? 'avanzada']);
            }
            $doc->forceFill(['estado' => 'en_flujo', 'flujo' => $flujo])->save();
            $this->audit->registrar('documento.flujo', 'documento', $doc->id, ['flujo' => $flujo, 'visadores' => count($visadores), 'firmantes' => count($firmantes)]);

            return $doc->refresh();
        });
    }

    private function pasoEnTurno(Documento $doc, Usuario $u, string $tipo): DocumentoPaso
    {
        if ($doc->estado !== 'en_flujo') {
            throw new FlujoException('El documento no está en flujo de visación/firma.');
        }
        $paso = $doc->pasos()->where('estado', 'pendiente')->orderBy('orden')->first();
        if (! $paso) {
            throw new FlujoException('No hay pasos pendientes.');
        }
        if ($paso->usuario_id !== $u->id || $paso->tipo !== $tipo) {
            throw new FlujoException('No es su turno en el flujo de este documento.');
        }

        return $paso;
    }

    public function visar(Documento $doc, Usuario $u, bool $aprobar, ?string $comentario): Documento
    {
        return DB::transaction(function () use ($doc, $u, $aprobar, $comentario) {
            $doc = Documento::query()->lockForUpdate()->findOrFail($doc->id);
            $paso = $this->pasoEnTurno($doc, $u, 'visacion');
            $paso->forceFill(['estado' => $aprobar ? 'completado' : 'rechazado', 'comentario' => $comentario, 'completado_en' => now()])->save();
            if (! $aprobar) {
                $doc->forceFill(['estado' => 'borrador'])->save(); // vuelve a edición; el flujo se redefine
            }
            $this->audit->registrar($aprobar ? 'documento.visar' : 'documento.rechazar', 'documento', $doc->id);

            return $doc->refresh();
        });
    }

    public function firmar(Documento $doc, Usuario $u, ?string $otp, ?string $tipoFirma = null): Documento
    {
        $doc = Documento::query()->with('tipo')->findOrFail($doc->id);
        $paso = $this->pasoEnTurno($doc, $u, 'firma');
        if (! $u->puede_firmar || ! $u->activo) {
            throw new FlujoException('No está habilitado(a) para firmar.');
        }
        $primera = $doc->pasos()->where('tipo', 'firma')->orderBy('orden')->first();
        if ($doc->tipo->firma_alcalde && $primera?->id === $paso->id && ! $this->delegaciones->puedeFirmarComoAlcalde($u, $doc->tipo)) {
            throw new FlujoException('Su delegación de firma no está vigente.');
        }
        $tipoFirma = $this->tipoFirmaEfectivo($paso, $tipoFirma);
        $cerrojo = Cache::lock('firma-paso:'.$paso->id, 90); // evita dos llamadas simultáneas a FirmaGob por el mismo paso (doble envío)
        if (! $cerrojo->get()) {
            throw new FlujoException('Su firma ya se está procesando. Espere unos segundos y revise el estado del documento.');
        }
        try {
            $tipoFirma === 'simple' ? $this->firmarSimple($doc, $paso, $u) : $this->firmarPaso($doc, $paso, $u, $otp);
        } finally {
            $cerrojo->release();
        }

        try {
            return $this->finalizar($doc);
        } catch (\Throwable $e) {
            report($e); // la firma ya quedó registrada; "finalizar" es reintentable

            return $doc->refresh();
        }
    }

    /** avanzada | simple según lo permitido en el paso y lo elegido por quien firma. */
    private function tipoFirmaEfectivo(DocumentoPaso $paso, ?string $elegido): string
    {
        $perm = $paso->firma_permitida ?: 'avanzada';
        $tipo = $elegido ?: ($perm === 'simple' ? 'simple' : 'avanzada');
        if (! in_array($tipo, ['avanzada', 'simple'], true) || ($perm !== 'ambas' && $perm !== $tipo)) {
            throw new FlujoException($perm === 'simple' ? 'Este paso se firma con firma electrónica simple.' : 'Este paso se firma con firma electrónica avanzada (FirmaGob).');
        }

        return $tipo;
    }

    /**
     * Firma electrónica SIMPLE (Ley 19.799): la persona autenticada en el sistema manifiesta su voluntad sobre el PDF íntegro (se verifica su
     * SHA-256). No usa FirmaGob ni OTP; queda registrada en el documento y en la auditoría. Solo en tipos de documento que la admiten.
     */
    private function firmarSimple(Documento $doc, DocumentoPaso $paso, Usuario $u): void
    {
        if (! $doc->tipo->permite_firma_simple) {
            throw new FlujoException('Este tipo de documento no admite firma electrónica simple.');
        }
        $pdf = $this->vault->get($doc->pdf_ruta, $doc->pdf_sha256); // verifica integridad antes de firmar
        DB::transaction(function () use ($doc, $paso, $pdf) {
            $paso = DocumentoPaso::query()->lockForUpdate()->findOrFail($paso->id);
            if ($paso->estado !== 'pendiente') {
                return; // idempotente
            }
            $paso->forceFill(['estado' => 'completado', 'simulada' => false, 'firma_tipo' => 'simple', 'checksum_original' => hash('sha256', $pdf), 'completado_en' => now()])->save();
            $this->audit->registrar('documento.firmar', 'documento', $doc->id, ['tipo' => 'simple'], true);
        });
    }

    private function firmarPaso(Documento $doc, DocumentoPaso $paso, Usuario $u, ?string $otp): void
    {
        $pdf = $this->vault->get($doc->pdf_ruta, $doc->pdf_sha256); // verifica integridad antes de firmar
        try {
            $res = $this->firma->firmar($pdf, $u->run, $otp, $doc->titulo);
        } catch (FirmaException $e) {
            $this->audit->registrar('documento.firma_fallida', 'documento', $doc->id, ['motivo' => $e->getMessage()]);
            throw new FlujoException($e->getMessage(), 0, $e); // falla cerrada: no se cambia ningún estado
        } finally {
            $otp = null; // el OTP nunca se almacena
        }
        $nuevo = $res['pdf'] === $pdf ? null : $this->vault->put($res['pdf']);
        DB::transaction(function () use ($doc, $paso, $res, $nuevo) {
            $paso = DocumentoPaso::query()->lockForUpdate()->findOrFail($paso->id);
            if ($paso->estado !== 'pendiente') {
                return; // idempotente
            }
            $paso->forceFill(['estado' => 'completado', 'firma_tipo' => 'avanzada', 'simulada' => $res['simulada'], 'checksum_original' => $res['checksum_original'], 'completado_en' => now()])->save();
            if ($nuevo) {
                $doc->forceFill(['pdf_ruta' => $nuevo['ruta'], 'pdf_sha256' => $nuevo['sha256'], 'pdf_firmado_ruta' => $nuevo['ruta'], 'pdf_firmado_sha256' => $nuevo['sha256']])->save();
            }
            $this->audit->registrar('documento.firmar', 'documento', $doc->id, ['simulada' => $res['simulada']], true);
        });
    }

    /** Idempotente y reintentable. Asigna número, código y copia pública cuando todos los pasos están completos. */
    public function finalizar(Documento $doc): Documento
    {
        $creados = [];
        try {
            return DB::transaction(function () use ($doc, &$creados) {
                $doc = Documento::query()->with(['tipo', 'pasos.usuario'])->lockForUpdate()->findOrFail($doc->id);
                if ($doc->estado === 'vigente') {
                    return $doc;
                }
                if ($doc->estado !== 'en_flujo' || $doc->pasos->isEmpty() || $doc->pasos->contains(fn ($p) => $p->estado !== 'completado')) {
                    throw new FlujoException('El documento aún tiene pasos pendientes.');
                }
                ['numero' => $n, 'anio' => $a] = $this->correlativos->siguiente($doc->tipo_documento_id);
                do {
                    $codigo = CorrelativoService::generarCodigo();
                } while (Documento::query()->where('codigo_validacion', $codigo)->exists());
                $doc->forceFill(['numero' => $n, 'anio' => $a, 'folio' => $this->folios->formatear($doc->tipo, $n, $a), 'codigo_validacion' => $codigo, 'fecha_firma' => now('America/Santiago'), 'estado' => 'vigente']);
                $sha = hash('sha256', $doc->html);
                $copia = $this->pdf->copiaPublica($doc, $sha);
                $f = $this->vault->put($copia);
                $creados[] = $f['ruta'];
                $doc->forceFill(['pdf_ruta' => $f['ruta'], 'pdf_sha256' => $f['sha256'], 'metadatos' => $this->mgdde($doc, $f['sha256'])])->save();
                $this->audit->registrar('documento.vigente', 'documento', $doc->id, ['numero' => $n, 'anio' => $a]);

                return $doc->refresh();
            });
        } catch (\Throwable $e) {
            foreach ($creados as $r) {
                $this->vault->delete($r);
            }
            throw $e;
        }
    }

    /** Metadatos del documento electrónico (MGDDE) - mapeo A VALIDAR contra la Guía Técnica vigente. */
    public function mgdde(Documento $d, string $sha256Pdf): array
    {
        return [
            'identificacion' => ['id' => $d->id, 'nombre' => $d->titulo, 'tipo' => $d->tipo->nombre, 'numero' => $d->numero, 'anio' => $d->anio, 'codigo_validacion' => $d->codigo_validacion],
            'descripcion' => ['resumen' => $d->resumen, 'palabras_clave' => $d->palabras_clave],
            'origen' => ['organismo' => 'I. Municipalidad de Santo Domingo', 'fecha_firma' => $d->fecha_firma?->toIso8601String()],
            'formato' => ['mime' => 'application/pdf', 'sha256' => $sha256Pdf],
            'seguridad' => ['nivel_acceso' => $d->nivel_acceso, 'sensible' => $d->sensible],
            'firmantes' => $d->pasos->where('tipo', 'firma')->map(fn ($p) => ['nombre' => $p->usuario->nombreCompleto(), 'simulada' => $p->simulada, 'tipo_firma' => $p->firma_tipo ?? 'avanzada'])->values()->all(),
            'version' => $d->version_actual,
        ];
    }

    public function leerPdf(Documento $d): string
    {
        return $this->vault->get((string) $d->pdf_ruta, (string) $d->pdf_sha256);
    }
}
