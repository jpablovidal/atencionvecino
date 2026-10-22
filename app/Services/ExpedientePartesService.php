<?php

namespace App\Services;

use App\Exceptions\FlujoException;
use App\Models\DocDigitalEntidad;
use App\Models\DocDigitalUsuario;
use App\Models\Expediente;
use App\Models\ExpedienteParte;
use App\Models\Notificacion;
use App\Models\Usuario;
use App\Support\Run;

/** Partes de un expediente (ciudadanos, funcionarios, externos y organismos públicos de DocDigital) y su canal de notificación. */
class ExpedientePartesService
{
    public function __construct(private readonly AuditoriaService $audit) {}

    /**
     * @param  array<string,mixed>  $d  tipo, rol, nombre, run, correo, usuario_id, dd_entidad_id, dd_usuarios[], canal, notas
     *
     * @throws FlujoException
     */
    public function agregar(Expediente $e, array $d, Usuario $u): ExpedienteParte
    {
        $tipo = (string) ($d['tipo'] ?? '');
        if (! isset(ExpedienteParte::TIPOS[$tipo])) {
            throw new FlujoException('Tipo de parte inválido.');
        }
        $rol = (string) ($d['rol'] ?? 'interesado');
        if (! isset(ExpedienteParte::ROLES[$rol])) {
            throw new FlujoException('Rol inválido.');
        }
        $canal = (string) ($d['canal'] ?? array_key_first(ExpedienteParte::CANALES[$tipo]));
        if (! isset(ExpedienteParte::CANALES[$tipo][$canal])) {
            throw new FlujoException('Ese canal no corresponde a este tipo de parte.');
        }
        $datos = ['expediente_id' => $e->id, 'tipo' => $tipo, 'rol' => $rol, 'canal' => $canal, 'creado_por' => $u->id, 'notas' => ($n = trim((string) ($d['notas'] ?? ''))) !== '' ? mb_substr($n, 0, 250) : null];
        $correo = trim((string) ($d['correo'] ?? ''));
        if ($correo !== '' && (! filter_var($correo, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $correo) || mb_strlen($correo) > 190)) {
            throw new FlujoException('El correo no es válido.');
        }
        $runIn = trim((string) ($d['run'] ?? ''));
        $run = $runIn !== '' ? Run::normalizar($runIn) : null;
        if ($runIn !== '' && $run === null) {
            throw new FlujoException('El RUN no es válido.');
        }

        if ($tipo === 'ciudadano' || $tipo === 'externo') {
            $nombre = trim((string) ($d['nombre'] ?? ''));
            if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 190) {
                throw new FlujoException('Indique el nombre (3 a 190 caracteres).');
            }
            if ($tipo === 'ciudadano' && ! $run) {
                throw new FlujoException('Para notificar a una persona se necesita su RUN.');
            }
            if ($canal === 'correo' && $correo === '') {
                throw new FlujoException('Para notificar por correo se necesita el correo electrónico.');
            }
            $datos += ['nombre' => $nombre, 'run' => $run, 'correo' => $correo !== '' ? $correo : null];
        } elseif ($tipo === 'funcionario') {
            $f = Usuario::query()->where('activo', true)->find((int) ($d['usuario_id'] ?? 0));
            if (! $f || ! $f->operaExpedientes()) {
                throw new FlujoException('Elija un funcionario(a) activo(a) que gestione expedientes.');
            }
            $datos += ['nombre' => $f->nombreCompleto(), 'run' => $f->run, 'correo' => $f->correo ?? null, 'usuario_id' => $f->id];
        } else {
            $ent = DocDigitalEntidad::query()->where('entidad_id', (int) ($d['dd_entidad_id'] ?? 0))->where('vigente', true)->first();
            if (! $ent) {
                throw new FlujoException('El organismo no figura en el directorio de DocDigital.');
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($d['dd_usuarios'] ?? [])), fn ($i) => $i > 0)));
            $personas = [];
            if ($ids) {
                $f = DocDigitalUsuario::query()->whereIn('usuario_id', $ids)->where('vigente', true)->get();
                if ($f->count() !== count($ids) || $f->contains(fn ($p) => (int) $p->entidad_id !== (int) $ent->entidad_id)) {
                    throw new FlujoException('Las personas deben estar habilitadas en DocDigital y pertenecer a ese organismo.');
                }
                $personas = $f->map(fn ($p) => ['id' => (int) $p->usuario_id, 'nombre' => $p->nombre])->values()->all();
            }
            $datos += ['nombre' => $ent->nombre, 'dd_entidad_id' => $ent->entidad_id, 'dd_usuarios' => $personas ? json_encode($personas, JSON_UNESCAPED_UNICODE) : null];
        }
        $dup = ExpedienteParte::query()->where('expediente_id', $e->id)->where('tipo', $tipo)
            ->when($tipo === 'funcionario', fn ($q) => $q->where('usuario_id', $datos['usuario_id']))
            ->when($tipo === 'organismo', fn ($q) => $q->where('dd_entidad_id', $datos['dd_entidad_id']))
            ->when(in_array($tipo, ['ciudadano', 'externo'], true), fn ($q) => $q->where('nombre', $datos['nombre'])->where('run', $datos['run']))->exists();
        if ($dup) {
            throw new FlujoException('Esa parte ya figura en el expediente.');
        }
        $p = ExpedienteParte::query()->create($datos);
        $this->audit->registrar('expediente.parte_agregar', 'expediente', $e->id, ['parte' => $p->id, 'tipo' => $tipo, 'canal' => $canal], $tipo === 'ciudadano');

        return $p;
    }

    public function quitar(ExpedienteParte $p, Usuario $u): void
    {
        if (Notificacion::query()->where('parte_id', $p->id)->exists()) {
            throw new FlujoException('No se puede quitar una parte a la que ya se le notificó.');
        }
        $this->audit->registrar('expediente.parte_quitar', 'expediente', $p->expediente_id, ['parte' => $p->id, 'tipo' => $p->tipo]);
        $p->delete();
    }
}
