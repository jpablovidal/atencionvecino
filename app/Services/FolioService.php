<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Folios según tipo de documento: formato configurable ({PREFIJO} {TIPO} {N} {AAAA} {AA}), relleno con ceros,
 * reinicio anual opcional, número inicial y ajuste del contador (solo hacia arriba, con motivo). El texto del folio
 * emitido se guarda en el documento: cambiar el formato no altera lo ya emitido.
 */
class FolioService
{
    public const FORMATO_DEFECTO = '{PREFIJO} N° {N}/{AAAA}';

    public function __construct(private readonly AuditoriaService $audit) {}

    public function formatear(TipoDocumento $t, int $numero, int $anio): string
    {
        $n = $t->folio_digitos > 0 ? str_pad((string) $numero, (int) $t->folio_digitos, '0', STR_PAD_LEFT) : (string) $numero;

        return strtr($t->folio_formato ?: self::FORMATO_DEFECTO, [
            '{PREFIJO}' => $t->prefijo, '{TIPO}' => $t->codigo, '{N}' => $n, '{AAAA}' => (string) $anio, '{AA}' => substr((string) $anio, -2),
        ]);
    }

    /** @throws FlujoException */
    public function validarFormato(string $formato, int $digitos, bool $reinicioAnual): void
    {
        $formato = trim($formato);
        if ($formato === '' || mb_strlen($formato) > 80) {
            throw new FlujoException('El formato del folio debe tener entre 1 y 80 caracteres.');
        }
        if (substr_count($formato, '{N}') !== 1) {
            throw new FlujoException('El formato debe incluir exactamente una vez {N} (número correlativo).');
        }
        $sin = strtr($formato, ['{PREFIJO}' => '', '{TIPO}' => '', '{N}' => '', '{AAAA}' => '', '{AA}' => '']);
        if (str_contains($sin, '{') || str_contains($sin, '}')) {
            throw new FlujoException('El formato solo admite los campos {PREFIJO}, {TIPO}, {N}, {AAAA} y {AA}.');
        }
        if (! preg_match('/^[\p{L}\p{N} °º\.\-_\/#\(\)]*$/u', $sin)) {
            throw new FlujoException('El formato contiene caracteres no permitidos (use letras, números, espacio y . - _ / # ° ( )).');
        }
        if ($digitos > 8) {
            throw new FlujoException('Los dígitos de relleno deben estar entre 0 y 8.');
        }
        if ($reinicioAnual && ! str_contains($formato, '{AAAA}') && ! str_contains($formato, '{AA}')) {
            throw new FlujoException('Con reinicio anual el formato debe incluir el año ({AAAA} o {AA}) para que los folios no se repitan.');
        }
    }

    /** @param array{folio_formato:string,folio_digitos:int,folio_reinicio_anual:bool,folio_inicio:int} $d */
    public function guardarConfiguracion(TipoDocumento $t, array $d, Usuario $u): TipoDocumento
    {
        $this->validarFormato($d['folio_formato'], (int) $d['folio_digitos'], (bool) $d['folio_reinicio_anual']);
        if ($d['folio_inicio'] < 1 || $d['folio_inicio'] > 99999999) {
            throw new FlujoException('El número inicial debe ser mayor o igual a 1.');
        }
        $antes = $t->only(['folio_formato', 'folio_digitos', 'folio_reinicio_anual', 'folio_inicio']);
        $t->forceFill([
            'folio_formato' => trim($d['folio_formato']), 'folio_digitos' => (int) $d['folio_digitos'],
            'folio_reinicio_anual' => (bool) $d['folio_reinicio_anual'], 'folio_inicio' => (int) $d['folio_inicio'],
        ])->save();
        $this->audit->registrar('folios.configurar', 'tipo_documento', $t->id, ['antes' => $antes, 'despues' => $t->only(array_keys($antes))]);

        return $t;
    }

    /** @return array<int,array{anio:int,ultimo:int}> */
    public function contadores(TipoDocumento $t): array
    {
        return DB::table('correlativos')->where('tipo_documento_id', $t->id)->orderByDesc('anio')->get(['anio', 'ultimo'])
            ->map(fn ($r) => ['anio' => (int) $r->anio, 'ultimo' => (int) $r->ultimo])->all();
    }

    /** Sube el contador (p. ej. al migrar desde otro sistema). Nunca baja: evitaría duplicados. anio=0 = contador continuo. */
    public function ajustarContador(TipoDocumento $t, int $anio, int $nuevoUltimo, string $motivo, Usuario $u): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 255) {
            throw new FlujoException('Indique el motivo del ajuste (5 a 255 caracteres).');
        }
        if ($anio !== 0 && ($anio < 2000 || $anio > 2200)) {
            throw new FlujoException('Año inválido.');
        }
        if ($t->folio_reinicio_anual === ($anio === 0)) {
            throw new FlujoException($t->folio_reinicio_anual ? 'Este tipo reinicia cada año: indique el año.' : 'Este tipo usa un contador continuo: el año debe ser 0.');
        }
        DB::transaction(function () use ($t, $anio, $nuevoUltimo, $motivo) {
            DB::table('correlativos')->insertOrIgnore(['tipo_documento_id' => $t->id, 'anio' => $anio, 'ultimo' => max(0, $t->folio_inicio - 1)]);
            $actual = (int) DB::table('correlativos')->where('tipo_documento_id', $t->id)->where('anio', $anio)->lockForUpdate()->value('ultimo');
            if ($nuevoUltimo < $actual) {
                throw new FlujoException("El contador no puede disminuir (actual: $actual): se generarían folios duplicados.");
            }
            DB::table('correlativos')->where('tipo_documento_id', $t->id)->where('anio', $anio)->update(['ultimo' => $nuevoUltimo]);
            $this->audit->registrar('folios.ajustar_contador', 'tipo_documento', $t->id, ['anio' => $anio, 'de' => $actual, 'a' => $nuevoUltimo, 'motivo' => $motivo]);
        });
    }
}
