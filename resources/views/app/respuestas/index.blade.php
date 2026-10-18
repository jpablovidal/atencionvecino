@extends('layouts.app')
@section('titulo', 'Respuestas finales')
@section('contenido')
<h1>Respuestas finales a solicitantes</h1>
<p class="lead-sub">Aquí llegan las respuestas que indicaron quienes gestionaron los expedientes: ve <strong>qué responder y con qué documentos</strong>. Se notifica por <strong>Casilla Única con copia al correo</strong>; la entrega física es excepcional.</p>
<div class="row mb-3"><div class="col-sm-6 col-lg-3 mb-2"><div class="card h-100"><div class="card-body"><div class="h3 mb-0">{{ $porDespachar }}</div><div class="small">Por despachar</div></div></div></div>
<div class="col-sm-6 col-lg-3 mb-2"><div class="card h-100"><div class="card-body"><div class="h3 mb-0">{{ $despachadas }}</div><div class="small">Despachadas</div></div></div></div></div>
<form method="get" class="form-inline mb-3" aria-label="Filtrar respuestas">
 <label class="mr-2" for="fe">Estado</label><select class="form-control mr-3" id="fe" name="estado">@foreach(['instruida'=>'Por despachar','despachada'=>'Despachadas','anulada'=>'Anuladas','todas'=>'Todas'] as $k=>$v)<option value="{{ $k }}" @selected($f['estado']===$k)>{{ $v }}</option>@endforeach</select>
 <label class="mr-2" for="fq">Folio, RUN o expediente</label><input class="form-control mr-3" id="fq" name="q" maxlength="40" value="{{ $f['q'] }}"><button class="btn btn-outline-primary">Filtrar</button></form>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Respuestas finales</caption>
<thead><tr><th scope="col">Instruida</th><th scope="col">Solicitud</th><th scope="col">Expediente</th><th scope="col">Solicitante</th><th scope="col">Entregar</th><th scope="col">Canal</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($pag as $x)<tr><td class="text-nowrap">{{ $x->created_at->setTimezone('America/Santiago')->format('d-m-Y H:i') }}<br><small>{{ $x->instructor?->nombreCompleto() }}</small></td><td>{{ $x->solicitud->folio }}</td><td>{{ $x->expediente->codigo }}<br><small>{{ $x->expediente->unidad?->nombre }}</small></td>
<td>{{ $x->solicitud->nombres }} {{ $x->solicitud->apellidos }}</td><td>{{ $x->items_count }} elemento(s)</td><td>{{ $canales[$x->canal] ?? $x->canal }}</td>
<td><span class="estado estado-{{ $x->estado }}">{{ $x->estado }}</span>@if($x->estado==='despachada')<br><small>{{ str_replace('_',' ',$x->entrega()) }}</small>@endif</td>
<td><a class="btn btn-sm btn-{{ $x->estado==='instruida' ? 'primary' : 'outline-primary' }}" href="{{ route('app.respuestas.ver', $x) }}">{{ $x->estado==='instruida' ? 'Responder' : 'Ver' }}<span class="sr-only"> solicitud {{ $x->solicitud->folio }}</span></a></td></tr>
@empty<tr><td colspan="8">No hay respuestas en este estado.</td></tr>@endforelse</tbody></table></div>
{{ $pag->links() }}
@endsection
