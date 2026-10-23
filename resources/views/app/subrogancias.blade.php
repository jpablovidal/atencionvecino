@extends('layouts.app')
@section('titulo', 'Subrogancias')
@section('contenido')
<h1>Subrogancias</h1>
<p class="lead-sub">Cuando un funcionario(a) está fuera de la oficina, otra persona lo <strong>subroga entre dos fechas y horas</strong>: mientras dure, el subrogante ve en su bandeja y gestiona los expedientes que el titular tiene a su cargo (es responsable, se le derivaron o tiene un paso de documento). Al vencer el plazo, o si usted la termina antes, el acceso se cierra solo. La subrogancia <strong>no entrega las firmas ni visaciones</strong> del titular (son personales; para firmar por otra persona use una delegación de firma) ni el acceso a expedientes restringidos.</p>
<section class="card mb-4" aria-labelledby="sub-n"><div class="card-body">
<h2 class="h5" id="sub-n">Nueva subrogancia</h2>
<form method="post" action="{{ route('app.subrogancias.crear') }}">@csrf
 <div class="form-row">
  <div class="form-group col-md-6"><label for="titular_id">Funcionario(a) que se ausenta (titular)</label><select class="form-control" id="titular_id" name="titular_id" required><option value="">Elija…</option>@foreach($personas as $p)<option value="{{ $p->id }}" @selected((string) old('titular_id')===(string) $p->id)>{{ $p->nombreCompleto() }}@if($p->cargo) — {{ $p->cargo }}@endif</option>@endforeach</select></div>
  <div class="form-group col-md-6"><label for="subrogante_id">Quien lo subroga</label><select class="form-control" id="subrogante_id" name="subrogante_id" required><option value="">Elija…</option>@foreach($personas as $p)<option value="{{ $p->id }}" @selected((string) old('subrogante_id')===(string) $p->id)>{{ $p->nombreCompleto() }}@if($p->cargo) — {{ $p->cargo }}@endif</option>@endforeach</select></div>
 </div>
 <div class="form-row">
  <div class="form-group col-md-4"><label for="desde">Desde (fecha y hora)</label><input class="form-control" type="datetime-local" id="desde" name="desde" value="{{ old('desde') }}" required></div>
  <div class="form-group col-md-4"><label for="hasta">Hasta (fecha y hora)</label><input class="form-control" type="datetime-local" id="hasta" name="hasta" value="{{ old('hasta') }}" required></div>
  <div class="form-group col-md-4"><label for="motivo">Motivo (opcional)</label><input class="form-control" id="motivo" name="motivo" maxlength="250" value="{{ old('motivo') }}" placeholder="Feriado legal, licencia, comisión de servicio…"></div>
 </div>
 <p class="small text-muted">Hora de Chile continental. Una persona no puede tener dos subrogancias que se crucen en el tiempo; el máximo es de {{ \App\Services\SubroganciaService::MAX_DIAS }} días.</p>
 <button class="btn btn-primary">Registrar subrogancia</button>
</form></div></section>

<form method="get" class="form-inline mb-3" aria-label="Filtrar subrogancias"><label class="mr-2" for="estado">Estado</label><select class="form-control mr-2" id="estado" name="estado"><option value="">Todas</option>@foreach(['vigente' => 'Vigentes', 'programada' => 'Programadas', 'vencida' => 'Vencidas', 'terminada' => 'Terminadas antes de tiempo'] as $k => $l)<option value="{{ $k }}" @selected(($f['estado'] ?? '')===$k)>{{ $l }}</option>@endforeach</select><button class="btn btn-outline-primary">Filtrar</button></form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Subrogancias</caption>
<thead><tr><th scope="col">Titular</th><th scope="col">Subrogante</th><th scope="col">Desde</th><th scope="col">Hasta</th><th scope="col">Estado</th><th scope="col">Motivo</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead><tbody>
@forelse($filas as $s) @php $est = $s->estado(); @endphp
<tr><td>{{ $s->titular->nombreCompleto() }}</td><td>{{ $s->subrogante->nombreCompleto() }}</td><td>{{ $s->desde->format('d-m-Y H:i') }}</td><td>{{ $s->hasta->format('d-m-Y H:i') }}</td>
<td><span class="estado {{ $est === 'vigente' ? 'estado-vigente' : '' }}">{{ ['vigente' => 'Vigente', 'programada' => 'Programada', 'vencida' => 'Vencida', 'terminada' => 'Terminada'][$est] }}</span></td><td>{{ $s->motivo ?? '—' }}</td>
<td>@if(in_array($est, ['vigente', 'programada'], true))<form method="post" action="{{ route('app.subrogancias.terminar', $s) }}" data-confirm="¿{{ $est === 'vigente' ? 'Terminar ahora' : 'Cancelar' }} esta subrogancia?">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $est === 'vigente' ? 'Terminar ahora' : 'Cancelar' }}<span class="sr-only"> la subrogancia de {{ $s->titular->nombreCompleto() }}</span></button></form>@endif</td></tr>
@empty<tr><td colspan="7" class="text-muted">No hay subrogancias con este criterio.</td></tr>@endforelse
</tbody></table></div>
@endsection
