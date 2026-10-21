<?php

namespace App\Services\Admin;

use App\Exceptions\FlujoException;
use App\Models\CalidadIniciativa;
use App\Models\CalidadPlataforma;
use App\Models\ConfigSitio;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Support\Carbon;

/**
 * Registro de cumplimiento de las normas técnicas que el sistema no puede deducir solo: Política de Gestión Documental (Decreto 10, arts. 3-4),
 * Coordinador(a) de Transformación Digital (Decreto 11, arts. 15-16), catálogo de plataformas y Plan de Mejora Continua (Decreto 11).
 */
class CumplimientoService
{
    public const CLAVE_POLITICA = 'politica_gestion_documental';

    public const CLAVE_COORDINADOR = 'coordinador_td';

    /** Plataformas que el sistema conoce y siembra en el catálogo la primera vez (editable). */
    public const PLATAFORMAS_BASE = [
        ['SGD — Sistema de Atención y Gestión Documental', 'propia', 'Plataforma de gestión de expedientes y documentos electrónicos del municipio (esta aplicación).'],
        ['ClaveÚnica', 'externa', 'Mecanismo oficial de autenticación (Gobierno Digital).'],
        ['FirmaGob', 'externa', 'Firma electrónica avanzada (Gobierno Digital).'],
        ['DocDigital', 'externa', 'Plataforma de comunicaciones oficiales (Gobierno Digital).'],
        ['Casilla Única / Plataforma de Notificaciones', 'externa', 'Notificaciones electrónicas a los interesados (Gobierno Digital).'],
        ['PISEE / red de interoperabilidad', 'externa', 'Interoperabilidad entre órganos del Estado; PISEE termina el 31-12-2026.'],
        ['Codificador / Gestor de Códigos del Estado', 'externa', 'Códigos de instituciones y procedimientos.'],
        ['Google Workspace (correo y repositorio)', 'externa', 'Envío de correos por Gmail API y repositorio documental en Google Drive.'],
    ];

    public function __construct(private readonly AuditoriaService $audit) {}

    // ---- Política de Gestión Documental

    /** @return array{decreto:string,fecha_aprobacion:?string,revision_anios:int,responsable:string,notas:string} */
    public function politica(): array
    {
        $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE_POLITICA)->value('valor'), true);

        return ['decreto' => (string) ($j['decreto'] ?? ''), 'fecha_aprobacion' => $j['fecha_aprobacion'] ?? null, 'revision_anios' => max(1, min(3, (int) ($j['revision_anios'] ?? 3))),
            'responsable' => (string) ($j['responsable'] ?? ''), 'notas' => (string) ($j['notas'] ?? '')];
    }

    /**
     * Estado de vigencia de la política: sin_registro, vigente, por_revisar (menos de 60 días) o vencida.
     *
     * @return array{estado:string,proxima:?string,texto:string}
     */
    public function estadoPolitica(?Carbon $hoy = null): array
    {
        $hoy ??= now('America/Santiago');
        $p = $this->politica();
        if (! $p['fecha_aprobacion']) {
            return ['estado' => 'sin_registro', 'proxima' => null, 'texto' => 'Aún no se registra la Política de Gestión Documental (Decreto 10, art. 3).'];
        }
        $prox = Carbon::parse($p['fecha_aprobacion'])->addYears($p['revision_anios']);
        if ($prox->lt($hoy->copy()->startOfDay())) {
            return ['estado' => 'vencida', 'proxima' => $prox->format('Y-m-d'), 'texto' => 'La revisión de la política venció el '.$prox->format('d-m-Y').' (se revisa entre cada 1 y 3 años).'];
        }
        if ($prox->lt($hoy->copy()->addDays(60))) {
            return ['estado' => 'por_revisar', 'proxima' => $prox->format('Y-m-d'), 'texto' => 'La política debe revisarse antes del '.$prox->format('d-m-Y').'.'];
        }

        return ['estado' => 'vigente', 'proxima' => $prox->format('Y-m-d'), 'texto' => 'Vigente; próxima revisión el '.$prox->format('d-m-Y').'.'];
    }

    /** @param array<string,mixed> $d */
    public function guardarPolitica(array $d, Usuario $u): void
    {
        $this->guardar(self::CLAVE_POLITICA, ['decreto' => trim((string) ($d['decreto'] ?? '')), 'fecha_aprobacion' => ($d['fecha_aprobacion'] ?? null) ?: null, 'revision_anios' => (int) $d['revision_anios'],
            'responsable' => trim((string) ($d['responsable'] ?? '')), 'notas' => trim((string) ($d['notas'] ?? ''))], $u, 'cumplimiento.politica');
    }

    // ---- Coordinador(a) de Transformación Digital

    /** @return array{titular:string,subrogante:string,decreto:string,fecha:?string,comunicado_en:?string} */
    public function coordinador(): array
    {
        $j = json_decode((string) ConfigSitio::query()->where('clave', self::CLAVE_COORDINADOR)->value('valor'), true);
        if (! is_array($j)) { // valores del Decreto Alcaldicio N° 2372 de 07-08-2026 citado en la Política de Privacidad (Decreto 2590)
            return ['titular' => 'Juan Pablo Vidal Araya', 'subrogante' => 'Alex Eduardo Sepúlveda Campos', 'decreto' => 'Decreto Alcaldicio N° 2372 del 07-08-2026', 'fecha' => '2026-08-07', 'comunicado_en' => null];
        }

        return ['titular' => (string) ($j['titular'] ?? ''), 'subrogante' => (string) ($j['subrogante'] ?? ''), 'decreto' => (string) ($j['decreto'] ?? ''), 'fecha' => $j['fecha'] ?? null, 'comunicado_en' => $j['comunicado_en'] ?? null];
    }

    /** @param array<string,mixed> $d */
    public function guardarCoordinador(array $d, Usuario $u): void
    {
        $this->guardar(self::CLAVE_COORDINADOR, ['titular' => trim((string) $d['titular']), 'subrogante' => trim((string) ($d['subrogante'] ?? '')), 'decreto' => trim((string) ($d['decreto'] ?? '')),
            'fecha' => ($d['fecha'] ?? null) ?: null, 'comunicado_en' => ($d['comunicado_en'] ?? null) ?: null], $u, 'cumplimiento.coordinador');
    }

    private function guardar(string $clave, array $valor, Usuario $u, string $accion): void
    {
        ConfigSitio::query()->updateOrCreate(['clave' => $clave], ['valor' => json_encode($valor, JSON_UNESCAPED_UNICODE), 'actualizado_por' => $u->id]);
        $this->audit->registrar($accion, 'config', $clave, [], true);
    }

    // ---- Catálogo de plataformas

    /** Siembra las plataformas conocidas la primera vez (no vuelve a crear las que se borraron o editaron: solo si el catálogo está vacío). */
    public function sembrarCatalogo(): int
    {
        if (CalidadPlataforma::query()->exists()) {
            return 0;
        }
        foreach (self::PLATAFORMAS_BASE as $i => [$n, $t, $d]) {
            CalidadPlataforma::query()->create(['nombre' => $n, 'tipo' => $t, 'descripcion' => $d, 'priorizada' => $i === 0, 'linea_base_fecha' => now('America/Santiago')->toDateString(), 'activa' => true,
                'linea_base' => $i === 0 ? 'Versión puesta en marcha; ver docs/LIMITACIONES.md y la matriz de cumplimiento de normas técnicas.' : null]);
        }

        return count(self::PLATAFORMAS_BASE);
    }

    /** @param array<string,mixed> $d */
    public function guardarPlataforma(array $d, Usuario $u, ?CalidadPlataforma $p = null): CalidadPlataforma
    {
        $p ??= new CalidadPlataforma;
        $p->fill(['nombre' => trim($d['nombre']), 'descripcion' => $d['descripcion'] ?? null, 'tipo' => $d['tipo'], 'responsable' => $d['responsable'] ?? null, 'linea_base' => $d['linea_base'] ?? null,
            'linea_base_fecha' => ($d['linea_base_fecha'] ?? null) ?: null, 'priorizada' => (bool) ($d['priorizada'] ?? false), 'vida_util_hasta' => ($d['vida_util_hasta'] ?? null) ?: null, 'activa' => (bool) ($d['activa'] ?? true)])->save();
        $this->audit->registrar('cumplimiento.plataforma', 'plataforma', $p->id, ['nombre' => $p->nombre], false);

        return $p;
    }

    // ---- Plan de Mejora Continua

    /** @param array<string,mixed> $d */
    public function guardarIniciativa(array $d, Usuario $u, ?CalidadIniciativa $i = null): CalidadIniciativa
    {
        if (! CalidadPlataforma::query()->whereKey($d['plataforma_id'])->exists()) {
            throw new FlujoException('La plataforma no existe.');
        }
        $i ??= new CalidadIniciativa;
        $i->fill(['plataforma_id' => $d['plataforma_id'], 'descripcion' => trim($d['descripcion']), 'objetivo' => $d['objetivo'] ?? null, 'etapa' => (int) $d['etapa'], 'subcaracteristica' => $d['subcaracteristica'] ?? null,
            'metrica' => $d['metrica'] ?? null, 'umbral_exitoso' => $d['umbral_exitoso'] ?? null, 'umbral_aceptable' => $d['umbral_aceptable'] ?? null, 'umbral_insuficiente' => $d['umbral_insuficiente'] ?? null,
            'responsable' => $d['responsable'] ?? null, 'plazo' => ($d['plazo'] ?? null) ?: null, 'costo_estimado' => ($d['costo_estimado'] ?? '') !== '' ? (int) $d['costo_estimado'] : null, 'riesgos' => $d['riesgos'] ?? null,
            'resultado' => $d['resultado'] ?? null, 'estado' => $d['estado']])->save();
        $this->audit->registrar('cumplimiento.iniciativa', 'iniciativa', $i->id, ['plataforma' => $i->plataforma_id, 'etapa' => $i->etapa, 'estado' => $i->estado], false);

        return $i;
    }

    /**
     * Subcaracterísticas distintas que abordan las iniciativas vigentes: el plan debe seleccionar al menos cuatro (Decreto 11, art. 6 n° 4).
     *
     * @return array{distintas:int,minimo:int,cumple:bool}
     */
    public function subcaracteristicas(): array
    {
        $n = CalidadIniciativa::query()->where('estado', '!=', 'descartada')->whereNotNull('subcaracteristica')->where('subcaracteristica', '!=', '')->distinct()->count('subcaracteristica');

        return ['distintas' => $n, 'minimo' => 4, 'cumple' => $n >= 4];
    }
}
