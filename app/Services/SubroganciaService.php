<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\Subrogancia;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Subrogancias entre fechas y horas. El subrogante gestiona, mientras la subrogancia esté vigente, los expedientes que el titular tiene a su cargo (es
 * responsable, o se le derivaron, o tiene un paso de documento). No hereda las firmas ni visaciones del titular (son personales: para firmar por otra
 * persona existen las delegaciones de firma) ni el acceso a lo restringido (se autoriza aparte), y tampoco puede subrogar a otro subrogante en cadena.
 */
class SubroganciaService
{
    public const MAX_DIAS = 366;

    public function __construct(private readonly AuditoriaService $audit) {}

    /** @var array<int,Collection<int,Usuario>> */
    private array $cache = [];

    /**
     * Titulares a quienes subroga hoy esta persona (vigentes y activos).
     *
     * @return Collection<int,Usuario>
     */
    public function titulares(Usuario $u): Collection
    {
        $k = $u->id.'|'.now()->format('Y-m-d H:i');

        return $this->cache[$k] ??= Usuario::query()->where('activo', true)
            ->whereIn('id', Subrogancia::query()->vigentes()->where('subrogante_id', $u->id)->select('titular_id'))->get();
    }

    /** @return array<int,int> */
    public function idsTitulares(Usuario $u): array
    {
        return $this->titulares($u)->pluck('id')->all();
    }

    public function subrogaA(Usuario $u, int $titularId): bool
    {
        return in_array($titularId, $this->idsTitulares($u), true);
    }

    /**
     * Crea una subrogancia.
     *
     * @throws FlujoException
     */
    public function crear(Usuario $titular, Usuario $subrogante, Carbon $desde, Carbon $hasta, ?string $motivo, Usuario $actor): Subrogancia
    {
        if ($titular->id === $subrogante->id) {
            throw new FlujoException('El titular y el subrogante deben ser personas distintas.');
        }
        foreach ([[$titular, 'titular'], [$subrogante, 'subrogante']] as [$p, $rol]) {
            if (! $p->activo || ! $p->operaExpedientes()) {
                throw new FlujoException('El '.$rol.' debe ser una persona activa que gestione expedientes.');
            }
        }
        if ($hasta->lessThanOrEqualTo($desde)) {
            throw new FlujoException('El término debe ser posterior al inicio.');
        }
        if ($hasta->lessThanOrEqualTo(now())) {
            throw new FlujoException('El término ya pasó: indique una fecha y hora futuras.');
        }
        if ($desde->diffInDays($hasta) > self::MAX_DIAS) {
            throw new FlujoException('Una subrogancia no puede durar más de '.self::MAX_DIAS.' días.');
        }
        $choque = Subrogancia::query()->where('activo', true)->where('titular_id', $titular->id)->where('desde', '<', $hasta)->where('hasta', '>', $desde)->where('hasta', '>', now())->exists();
        if ($choque) {
            throw new FlujoException('Esa persona ya tiene una subrogancia que se cruza con esas fechas y horas.');
        }
        $s = DB::transaction(fn () => Subrogancia::query()->create([
            'titular_id' => $titular->id, 'subrogante_id' => $subrogante->id, 'desde' => $desde, 'hasta' => $hasta,
            'motivo' => $motivo !== null && trim($motivo) !== '' ? mb_substr(trim($motivo), 0, 250) : null, 'activo' => true, 'creado_por' => $actor->id,
        ]));
        $this->audit->registrar('subrogancia.crear', 'subrogancia', $s->id, ['titular' => $titular->id, 'subrogante' => $subrogante->id, 'desde' => $desde->toDateTimeString(), 'hasta' => $hasta->toDateTimeString()], true);

        return $s;
    }

    /**
     * Termina una subrogancia antes de tiempo (o cancela una programada).
     *
     * @throws FlujoException
     */
    public function terminar(Subrogancia $s, Usuario $actor): void
    {
        if (! in_array($s->estado(), ['vigente', 'programada'], true)) {
            throw new FlujoException('Esa subrogancia ya no está vigente ni programada.');
        }
        $s->forceFill(['activo' => false, 'terminada_en' => now(), 'terminada_por' => $actor->id])->save();
        $this->cache = [];
        $this->audit->registrar('subrogancia.terminar', 'subrogancia', $s->id, ['titular' => $s->titular_id, 'subrogante' => $s->subrogante_id], true);
    }
}
