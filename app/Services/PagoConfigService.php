<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\PagoFirmante;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Configuración del decreto de pago y del egreso: tipos de documento (con su correlativo propio), firmantes y visadores en orden.
 * El correlativo usa el mismo mecanismo de folios del sistema (formato, relleno, reinicio anual, ajuste solo hacia arriba).
 */
class PagoConfigService
{
    /** clave => [código, nombre, prefijo, firma del alcalde, admite firma simple] */
    public const TIPOS = [
        'decreto_pago' => ['DECRETO_PAGO', 'Decreto de pago', 'Decreto de pago', true, false],
        'egreso' => ['EGRESO', 'Egreso', 'Egreso', false, true],
    ];

    public const MAX_FIRMANTES = 8;

    public function __construct(private readonly AuditoriaService $audit, private readonly FolioService $folios) {}

    private function exigir(Usuario $u): void
    {
        if (! $u->tienePermiso('decretopago.configurar')) {
            throw new FlujoException('No tiene permiso para configurar el decreto de pago.');
        }
    }

    private function claveValida(string $doc): void
    {
        if (! isset(self::TIPOS[$doc])) {
            throw new FlujoException('Documento inválido.');
        }
    }

    /** Tipo de documento del decreto de pago o del egreso; se crea la primera vez (luego se configura como cualquier tipo). */
    public function tipo(string $doc): TipoDocumento
    {
        $this->claveValida($doc);
        [$codigo, $nombre, $prefijo, $alcalde, $simple] = self::TIPOS[$doc];

        return TipoDocumento::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'prefijo' => $prefijo, 'firma_alcalde' => $alcalde, 'firmas_agil' => 1, 'firmas_clasico' => 2, 'activo' => true, 'permite_firma_simple' => $simple]);
    }

    /** @return Collection<int,PagoFirmante> */
    public function firmantes(string $doc, bool $soloActivos = false): Collection
    {
        $q = PagoFirmante::query()->with('usuario')->where('documento', $doc)->orderBy('orden')->orderBy('id');

        return ($soloActivos ? $q->where('activo', true) : $q)->get();
    }

    public function agregar(Usuario $actor, string $doc, int $usuarioId, string $cargo, string $funcion, string $firmaTipo): PagoFirmante
    {
        $this->exigir($actor);
        $tipo = $this->tipo($doc);
        $cargo = mb_substr(TramiteService::limpiarTexto($cargo), 0, 120);
        if (mb_strlen($cargo) < 3) {
            throw new FlujoException('Indique el cargo (mínimo 3 caracteres).');
        }
        if (! isset(PagoFirmante::FUNCIONES[$funcion]) || ! isset(PagoFirmante::FIRMAS[$firmaTipo])) {
            throw new FlujoException('Función o tipo de firma inválido.');
        }
        $u = Usuario::query()->where('activo', true)->find($usuarioId) ?? throw new FlujoException('La persona no existe o está inactiva.');
        if (! $u->operaExpedientes()) {
            throw new FlujoException('Solo funcionarios, alcalde o secretarios pueden visar o firmar.');
        }
        if ($funcion === 'firma' && ! $u->puede_firmar) {
            throw new FlujoException($u->nombreCompleto().' no está habilitado(a) para firmar.');
        }
        if ($funcion === 'visacion') {
            $firmaTipo = 'avanzada'; // la visación no es una firma
        }
        if ($firmaTipo !== 'avanzada' && ! $tipo->permite_firma_simple) {
            throw new FlujoException('Este documento solo admite firma electrónica avanzada.');
        }
        if (PagoFirmante::query()->where('documento', $doc)->where('usuario_id', $u->id)->where('activo', true)->exists()) {
            throw new FlujoException('La persona ya está en la lista de este documento.');
        }
        if (PagoFirmante::query()->where('documento', $doc)->where('activo', true)->count() >= self::MAX_FIRMANTES) {
            throw new FlujoException('Máximo '.self::MAX_FIRMANTES.' participantes por documento.');
        }
        $orden = (int) PagoFirmante::query()->where('documento', $doc)->max('orden') + 1;
        $f = PagoFirmante::query()->create(['documento' => $doc, 'usuario_id' => $u->id, 'cargo' => $cargo, 'funcion' => $funcion, 'firma_tipo' => $firmaTipo, 'orden' => $orden, 'activo' => true]);
        $this->audit->registrar('pago.firmante_agregar', 'pago_firmante', $f->id, ['documento' => $doc, 'funcion' => $funcion, 'firma' => $firmaTipo]);

        return $f;
    }

    public function quitar(Usuario $actor, PagoFirmante $f): void
    {
        $this->exigir($actor);
        $f->delete();
        $this->audit->registrar('pago.firmante_quitar', 'pago_firmante', $f->id, ['documento' => $f->documento]);
    }

    public function alternar(Usuario $actor, PagoFirmante $f): void
    {
        $this->exigir($actor);
        if (! $f->activo && PagoFirmante::query()->where('documento', $f->documento)->where('usuario_id', $f->usuario_id)->where('activo', true)->exists()) {
            throw new FlujoException('La persona ya está activa en este documento.');
        }
        $f->forceFill(['activo' => ! $f->activo])->save();
        $this->audit->registrar('pago.firmante_estado', 'pago_firmante', $f->id, ['activo' => $f->activo]);
    }

    public function mover(Usuario $actor, PagoFirmante $f, string $sentido): void
    {
        $this->exigir($actor);
        DB::transaction(function () use ($f, $sentido) {
            $lista = PagoFirmante::query()->where('documento', $f->documento)->orderBy('orden')->orderBy('id')->lockForUpdate()->get()->values();
            $i = $lista->search(fn ($x) => $x->id === $f->id);
            $j = $sentido === 'subir' ? $i - 1 : $i + 1;
            if ($i === false || $j < 0 || $j >= $lista->count()) {
                return;
            }
            $lista->splice($j, 0, [$lista->splice($i, 1)->first()]);
            foreach ($lista->values() as $k => $x) {
                if ((int) $x->orden !== $k + 1) {
                    $x->forceFill(['orden' => $k + 1])->save();
                }
            }
        });
        $this->audit->registrar('pago.firmante_orden', 'pago_firmante', $f->id, ['sentido' => $sentido]);
    }

    /**
     * Flujo del documento según los firmantes activos: visadores y firmantes en orden. La cantidad de firmantes debe coincidir con la que
     * exige el tipo de documento (flujo ágil o clásico).
     *
     * @return array{flujo:string,visadores:array<int,int>,firmantes:array<int,int>,permitidas:array<int,string>}
     */
    public function flujo(string $doc): array
    {
        $tipo = $this->tipo($doc);
        $act = $this->firmantes($doc, true);
        $firmas = $act->where('funcion', 'firma');
        $n = $firmas->count();
        $flujo = $n === $tipo->firmas_agil ? 'agil' : ($n === $tipo->firmas_clasico ? 'clasico' : null);
        if ($flujo === null) {
            throw new FlujoException("Configure en «Firmantes» exactamente {$tipo->firmas_agil} o {$tipo->firmas_clasico} firmante(s) activo(s) para {$tipo->nombre} (hay $n).");
        }

        return ['flujo' => $flujo, 'visadores' => $act->where('funcion', 'visacion')->pluck('usuario_id')->map(fn ($i) => (int) $i)->all(),
            'firmantes' => $firmas->pluck('usuario_id')->map(fn ($i) => (int) $i)->all(), 'permitidas' => $firmas->pluck('firma_tipo', 'usuario_id')->all()];
    }

    /** @param array{folio_formato:string,folio_digitos:int,folio_reinicio_anual:bool,folio_inicio:int} $d */
    public function guardarCorrelativo(Usuario $actor, string $doc, array $d): TipoDocumento
    {
        $this->exigir($actor);

        return $this->folios->guardarConfiguracion($this->tipo($doc), $d, $actor);
    }

    public function ajustarContador(Usuario $actor, string $doc, int $anio, int $ultimo, string $motivo): void
    {
        $this->exigir($actor);
        $this->folios->ajustarContador($this->tipo($doc), $anio, $ultimo, $motivo, $actor);
    }
}
