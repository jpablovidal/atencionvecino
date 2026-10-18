<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\ConfigSitio;
use App\Models\Solicitud;
use App\Models\TransparenciaActivaItem;
use App\Models\TransparenciaActivaRegistro;
use App\Models\TransparenciaDerivacion;
use App\Models\TransparenciaFeriado;
use App\Models\TransparenciaRequerimiento;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Support\Plazos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transparencia pasiva (solicitudes de acceso a la información, Ley 20.285) y activa (publicaciones mensuales obligatorias).
 * Los plazos legales por defecto son A VALIDAR (docs/NORMATIVA.md §9); se administran desde el panel.
 */
class TransparenciaService
{
    public const CANALES = ['docdigital' => 'DocDigital', 'oficio' => 'Oficio', 'correo' => 'Correo electrónico', 'otro' => 'Otro'];

    /** clave => [etiqueta, defecto, mínimo, máximo] */
    public const PARAMETROS = [
        'prorroga_dias' => ['Días de la prórroga (única) de una solicitud', 10, 1, 30],
        'requerimiento_dias' => ['Plazo por defecto de un requerimiento a una dirección (días)', 5, 1, 30],
        'activa_dia_limite' => ['Día del mes hasta el que debe publicarse la transparencia activa (por defecto)', 10, 1, 28],
    ];

    public function __construct(private readonly AuditoriaService $audit) {}

    // ---------------------------------------------------------------- parámetros
    public function param(string $k): int
    {
        [, $defecto, $min, $max] = self::PARAMETROS[$k];
        $v = ConfigSitio::query()->where('clave', 'transparencia_'.$k)->value('valor');

        return is_numeric($v) ? max($min, min($max, (int) $v)) : $defecto;
    }

    /** @param array<string,mixed> $d */
    public function guardarParametros(Usuario $u, array $d): void
    {
        $this->exigir($u, 'transparencia.gestionar');
        foreach (self::PARAMETROS as $k => [, , $min, $max]) {
            if (! isset($d[$k])) {
                continue;
            }
            $v = filter_var($d[$k], FILTER_VALIDATE_INT);
            if ($v === false || $v < $min || $v > $max) {
                throw new FlujoException("«{$k}» debe estar entre {$min} y {$max}.");
            }
            ConfigSitio::query()->updateOrCreate(['clave' => 'transparencia_'.$k], ['valor' => (string) $v, 'actualizado_por' => $u->id]);
        }
        $this->audit->registrar('transparencia.parametros', null, null, array_intersect_key($d, self::PARAMETROS));
    }

    private function exigir(Usuario $u, string $permiso): void
    {
        if (! $u->tienePermiso($permiso)) {
            throw new FlujoException('No tiene permiso para esta acción.');
        }
    }

    private function texto(?string $t, int $min, int $max, string $campo, bool $multi = true): string
    {
        $t = TramiteService::limpiarTexto($t, $multi);
        if (mb_strlen($t) < $min || mb_strlen($t) > $max) {
            throw new FlujoException("{$campo}: entre {$min} y {$max} caracteres.");
        }

        return $t;
    }

    private function exigirSai(Solicitud $s): void
    {
        if (! $s->tramite->es_transparencia) {
            throw new FlujoException('La solicitud no es una solicitud de acceso a la información pública.');
        }
        if (in_array($s->estado, ['finalizada', 'cerrada'], true)) {
            throw new FlujoException('La solicitud ya terminó.');
        }
    }

    // ---------------------------------------------------------------- pasiva
    public function prorrogar(Solicitud $s, Usuario $u, string $motivo): Solicitud
    {
        $this->exigir($u, 'transparencia.gestionar');

        return DB::transaction(function () use ($s, $u, $motivo) {
            $s = Solicitud::query()->with('tramite')->lockForUpdate()->findOrFail($s->id);
            $this->exigirSai($s);
            if ($s->prorroga_dias) {
                throw new FlujoException('La solicitud ya fue prorrogada: la prórroga es única.');
            }
            if ($s->estado === 'devuelta') {
                throw new FlujoException('La solicitud está devuelta al solicitante: el plazo está suspendido.');
            }
            if (now(Plazos::TZ)->greaterThan(Plazos::deSolicitud($s))) {
                throw new FlujoException('El plazo ya venció: la prórroga debe decidirse antes del vencimiento.');
            }
            $motivo = $this->texto($motivo, 10, 1000, 'El motivo de la prórroga');
            $s->forceFill(['prorroga_dias' => $this->param('prorroga_dias'), 'prorroga_motivo' => $motivo, 'prorrogada_en' => now(), 'prorrogada_por' => $u->id])->save();
            $this->audit->registrar('transparencia.prorroga', 'solicitud', $s->folio, ['dias' => $s->prorroga_dias]);

            return $s;
        });
    }

    public function requerir(Solicitud $s, Usuario $u, int $unidadId, string $instruccion, ?int $dias = null): TransparenciaRequerimiento
    {
        $this->exigir($u, 'transparencia.gestionar');
        $s->loadMissing('tramite', 'expediente');
        $this->exigirSai($s);
        if (! $s->expediente || $s->expediente->estado !== 'abierto') {
            throw new FlujoException('Asigne la solicitud (expediente abierto) antes de requerir información a una dirección.');
        }
        $unidad = Unidad::query()->where('activo', true)->find($unidadId) ?? throw new FlujoException('Dirección inválida.');
        $dias ??= $this->param('requerimiento_dias');
        if ($dias < 1 || $dias > 30) {
            throw new FlujoException('El plazo del requerimiento debe estar entre 1 y 30 días.');
        }
        $instruccion = $this->texto($instruccion, 10, 2000, 'La instrucción');
        $vence = Plazos::vencimiento(now(Plazos::TZ), $dias, $s->tramite->plazo_habiles ? true : null);
        $r = TransparenciaRequerimiento::query()->create(['solicitud_id' => $s->id, 'unidad_id' => $unidad->id, 'instruccion' => $instruccion, 'plazo_dias' => $dias, 'vence_en' => $vence, 'estado' => 'pendiente', 'creado_por' => $u->id]);
        $this->audit->registrar('transparencia.requerimiento', 'solicitud', $s->folio, ['unidad' => $unidad->codigo, 'dias' => $dias]);

        return $r;
    }

    public function responder(TransparenciaRequerimiento $r, Usuario $u, string $respuesta): TransparenciaRequerimiento
    {
        $this->exigir($u, 'transparencia.responder');

        return DB::transaction(function () use ($r, $u, $respuesta) {
            $r = TransparenciaRequerimiento::query()->lockForUpdate()->findOrFail($r->id);
            if (! $u->unidad_id || (int) $u->unidad_id !== (int) $r->unidad_id) {
                throw new FlujoException('Este requerimiento corresponde a otra dirección.');
            }
            if ($r->estado !== 'pendiente') {
                throw new FlujoException('El requerimiento ya fue respondido.');
            }
            $respuesta = $this->texto($respuesta, 5, 4000, 'La respuesta');
            $r->forceFill(['estado' => 'respondido', 'respuesta' => $respuesta, 'respondido_por' => $u->id, 'respondido_en' => now()])->save();
            $this->audit->registrar('transparencia.respuesta', 'requerimiento', $r->id, ['solicitud_id' => $r->solicitud_id]);

            return $r;
        });
    }

    /** Declara la incompetencia y deriva la solicitud a otro órgano (Ley 20.285, art. 13): registra el envío y cierra el expediente. */
    public function derivarOtroOrgano(Solicitud $s, Usuario $u, array $d): TransparenciaDerivacion
    {
        $this->exigir($u, 'transparencia.gestionar');
        $s->loadMissing('tramite', 'expediente');
        $this->exigirSai($s);
        if (! $s->expediente || $s->expediente->estado !== 'abierto') {
            throw new FlujoException('Asigne la solicitud (expediente abierto) antes de derivarla a otro órgano.');
        }
        $organo = $this->texto($d['organo'] ?? '', 3, 190, 'El órgano', false);
        $motivo = $this->texto($d['motivo'] ?? '', 10, 1000, 'El motivo');
        $canal = (string) ($d['canal'] ?? '');
        if (! isset(self::CANALES[$canal])) {
            throw new FlujoException('Canal de envío inválido.');
        }
        $ref = isset($d['referencia']) && $d['referencia'] !== '' ? $this->texto($d['referencia'], 1, 120, 'La referencia', false) : null;
        try {
            $fecha = Carbon::createFromFormat('Y-m-d', (string) ($d['fecha'] ?? ''), Plazos::TZ)->startOfDay();
        } catch (\Throwable) {
            throw new FlujoException('Fecha de envío inválida.');
        }
        if ($fecha->isFuture() || $fecha->lt($s->created_at->copy()->setTimezone(Plazos::TZ)->startOfDay())) {
            throw new FlujoException('La fecha de envío no puede ser futura ni anterior al ingreso de la solicitud.');
        }

        return DB::transaction(function () use ($s, $u, $organo, $motivo, $canal, $ref, $fecha) {
            $x = TransparenciaDerivacion::query()->create(['solicitud_id' => $s->id, 'organo' => $organo, 'motivo' => $motivo, 'fecha' => $fecha->format('Y-m-d'), 'canal' => $canal, 'referencia' => $ref, 'creado_por' => $u->id]);
            app(ExpedienteService::class)->cerrar($s->expediente); // falla (y revierte) si hay documentos en flujo de firma
            $this->audit->registrar('transparencia.derivar_organo', 'solicitud', $s->folio, ['canal' => $canal]);

            return $x;
        });
    }

    public function agregarFeriado(Usuario $u, string $fecha, string $nombre): TransparenciaFeriado
    {
        $this->exigir($u, 'transparencia.gestionar');
        try {
            $f = Carbon::createFromFormat('Y-m-d', $fecha, Plazos::TZ);
        } catch (\Throwable) {
            throw new FlujoException('Fecha inválida.');
        }
        if ($f->year < 2020 || $f->year > 2100) {
            throw new FlujoException('Fecha fuera de rango.');
        }
        if (TransparenciaFeriado::query()->where('fecha', $f->format('Y-m-d'))->exists()) {
            throw new FlujoException('Ese feriado ya está registrado.');
        }
        $r = TransparenciaFeriado::query()->create(['fecha' => $f->format('Y-m-d'), 'nombre' => $this->texto($nombre, 3, 120, 'El nombre', false)]);
        Plazos::olvidarFeriados();
        $this->audit->registrar('transparencia.feriado_agregar', 'feriado', $r->id, ['fecha' => $r->fecha]);

        return $r;
    }

    public function quitarFeriado(Usuario $u, TransparenciaFeriado $f): void
    {
        $this->exigir($u, 'transparencia.gestionar');
        $id = $f->id;
        $f->delete();
        Plazos::olvidarFeriados();
        $this->audit->registrar('transparencia.feriado_quitar', 'feriado', $id);
    }

    // ---------------------------------------------------------------- activa
    /** Periodo (AAAA-MM) cuya información debe estar publicada: el mes anterior al mes en curso. */
    public function periodoVigente(?Carbon $hoy = null): string
    {
        return ($hoy ?? now(Plazos::TZ))->copy()->startOfMonth()->subMonth()->format('Y-m');
    }

    /** @return array{estado:string,periodo:string,limite:Carbon,registro:?TransparenciaActivaRegistro} estado: al_dia|por_vencer|pendiente|vencida */
    public function estadoActiva(TransparenciaActivaItem $i, ?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? now(Plazos::TZ))->copy()->setTimezone(Plazos::TZ);
        $periodo = $this->periodoVigente($hoy);
        $limite = $hoy->copy()->startOfMonth()->day(min((int) $i->dia_limite, $hoy->daysInMonth))->endOfDay();
        $reg = TransparenciaActivaRegistro::query()->where('item_id', $i->id)->where('periodo', $periodo)->orderByDesc('id')->first();
        $estado = $reg ? 'al_dia' : ($hoy->greaterThan($limite) ? 'vencida' : (Plazos::situacion($limite, $hoy) === 'por_vencer' ? 'por_vencer' : 'pendiente'));

        return ['estado' => $estado, 'periodo' => $periodo, 'limite' => $limite, 'registro' => $reg];
    }

    public function registrarActiva(Usuario $u, TransparenciaActivaItem $i, string $periodo, string $publicadoEn, ?string $url, ?string $obs): TransparenciaActivaRegistro
    {
        $this->exigir($u, 'transparencia.gestionar');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            throw new FlujoException('Periodo inválido (AAAA-MM).');
        }
        if ($periodo > now(Plazos::TZ)->format('Y-m')) {
            throw new FlujoException('No se puede registrar la publicación de un periodo futuro.');
        }
        try {
            $f = Carbon::createFromFormat('Y-m-d', $publicadoEn, Plazos::TZ)->startOfDay();
        } catch (\Throwable) {
            throw new FlujoException('Fecha de publicación inválida.');
        }
        if ($f->isFuture()) {
            throw new FlujoException('La fecha de publicación no puede ser futura.');
        }
        $url = $url !== null && $url !== '' ? $this->url($url) : null;
        $obs = $obs !== null && $obs !== '' ? $this->texto($obs, 1, 300, 'La observación', false) : null;
        $r = TransparenciaActivaRegistro::query()->create(['item_id' => $i->id, 'periodo' => $periodo, 'publicado_en' => $f->format('Y-m-d'), 'url' => $url, 'observacion' => $obs, 'registrado_por' => $u->id, 'created_at' => now()]);
        $this->audit->registrar('transparencia.activa_registro', 'activa_item', $i->codigo, ['periodo' => $periodo]);

        return $r;
    }

    /** @param array<string,mixed> $d codigo (solo al crear), nombre, unidad_id, dia_limite, url_publicacion, activo */
    public function guardarItem(Usuario $u, array $d, ?TransparenciaActivaItem $i = null): TransparenciaActivaItem
    {
        $this->exigir($u, 'transparencia.gestionar');
        $dia = filter_var($d['dia_limite'] ?? $this->param('activa_dia_limite'), FILTER_VALIDATE_INT);
        if ($dia === false || $dia < 1 || $dia > 28) {
            throw new FlujoException('El día límite debe estar entre 1 y 28.');
        }
        $unidad = ! empty($d['unidad_id']) ? (Unidad::query()->where('activo', true)->find((int) $d['unidad_id'])?->id ?? throw new FlujoException('Dirección inválida.')) : null;
        $campos = ['nombre' => $this->texto($d['nombre'] ?? '', 5, 190, 'El nombre', false), 'unidad_id' => $unidad, 'dia_limite' => $dia,
            'url_publicacion' => ! empty($d['url_publicacion']) ? $this->url((string) $d['url_publicacion']) : null, 'activo' => ! empty($d['activo'])];
        if ($i) {
            $i->forceFill($campos)->save();
        } else {
            $codigo = strtoupper((string) ($d['codigo'] ?? ''));
            if (! preg_match('/^[A-Z0-9_]{3,30}$/', $codigo) || TransparenciaActivaItem::query()->where('codigo', $codigo)->exists()) {
                throw new FlujoException('Código inválido o repetido (3 a 30 letras mayúsculas, números o _).');
            }
            $i = TransparenciaActivaItem::query()->create($campos + ['codigo' => $codigo]);
        }
        $this->audit->registrar('transparencia.activa_item', 'activa_item', $i->codigo);

        return $i;
    }

    private function url(string $u): string
    {
        $u = trim($u);
        if (mb_strlen($u) > 300 || ! preg_match('#^https://[^\s<>"\']+$#i', $u) || filter_var($u, FILTER_VALIDATE_URL) === false) {
            throw new FlujoException('La dirección web debe comenzar con https:// y no contener espacios.');
        }

        return $u;
    }
}
