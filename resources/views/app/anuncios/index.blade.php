@extends('layouts.app')
@section('titulo', 'Anuncios')
@section('contenido')
<h1>Anuncios del sitio</h1>
<p class="lead-sub">Los anuncios vigentes se muestran en una ventana (modal) apenas se carga el sitio, una vez por visita y por anuncio. Pueden llevar título, descripción, una imagen o un video, y se dirigen a vecinos(as) o a funcionarios(as).</p>
<p><a class="btn btn-primary" href="{{ route('app.anuncios.nuevo') }}">Nuevo anuncio</a></p>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Anuncios</caption>
<thead><tr><th scope="col">Título</th><th scope="col">Dirigido a</th><th scope="col">Medio</th><th scope="col">Vigencia</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
@forelse($anuncios as $a)<tr><td>{{ $a->titulo }}</td><td>{{ $audiencias[$a->audiencia] ?? $a->audiencia }}</td><td>{{ $a->medio_tipo ? ucfirst($a->medio_tipo) : '—' }}</td>
<td class="text-nowrap">{{ $a->inicia_en ? \Illuminate\Support\Carbon::parse($a->inicia_en)->format('d-m-Y') : 'desde ya' }} → {{ $a->termina_en ? \Illuminate\Support\Carbon::parse($a->termina_en)->format('d-m-Y') : 'sin término' }}</td>
<td>@if($a->vigente())<span class="chip chip-claro">Se muestra ahora</span>@elseif($a->activo)<span class="chip chip-claro">Programado o vencido</span>@else<span class="chip chip-claro">Desactivado</span>@endif</td>
<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('app.anuncios.editar', $a) }}">Editar<span class="sr-only"> {{ $a->titulo }}</span></a>
<form method="post" action="{{ route('app.anuncios.alternar', $a) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $a->activo ? 'Desactivar' : 'Activar' }}<span class="sr-only"> {{ $a->titulo }}</span></button></form>
<form method="post" action="{{ route('app.anuncios.eliminar', $a) }}" class="d-inline" data-confirm="¿Eliminar el anuncio «{{ $a->titulo }}»? No se puede deshacer.">@csrf<button class="btn btn-sm btn-outline-secondary">Eliminar<span class="sr-only"> {{ $a->titulo }}</span></button></form></td></tr>
@empty<tr><td colspan="6">Aún no hay anuncios.</td></tr>@endforelse</tbody></table></div>
@endsection
