@extends('layouts.app')
@section('titulo', 'Informe del Plan de Mejora Continua')
@section('contenido')
<p class="d-print-none"><a href="{{ route('app.admin.cumplimiento') }}#pla">&larr; Cumplimiento normativo</a> · <button type="button" class="btn btn-sm btn-outline-secondary" data-imprimir>Imprimir o guardar como PDF</button></p>
<h1>Informe del Plan de Mejora Continua</h1>
<p>I. Municipalidad de Santo Domingo · Norma Técnica de Calidad y Funcionamiento (Decreto 11, art. 9 letra c). Generado el {{ now('America/Santiago')->format('d-m-Y H:i') }} (hora de Chile).</p>
<h2 class="h5">Responsables</h2>
<ul><li>Coordinador(a) de Transformación Digital: {{ $coordinador['titular'] }} (subrogante: {{ $coordinador['subrogante'] ?: '—' }}); {{ $coordinador['decreto'] }}.</li>
<li>Política de Gestión Documental: {{ $politica['decreto'] ?: 'sin registro' }} — {{ $estadoPolitica['texto'] }}</li>
<li>Subcaracterísticas seleccionadas: {{ $subcar['distintas'] }} (mínimo {{ $subcar['minimo'] }}) — {{ $subcar['cumple'] ? 'cumple' : 'no cumple todavía' }}.</li></ul>
@foreach($plataformas as $p)
<h2 class="h5 mt-4">{{ $p->nombre }}@if($p->priorizada) <small>(priorizada)</small>@endif</h2>
<p class="small mb-1">Línea de base {{ $p->linea_base_fecha?->format('d-m-Y') }}: {{ $p->linea_base ?: 'sin registro' }}@if($p->vida_util_hasta) · vida útil estimada hasta {{ $p->vida_util_hasta->format('d-m-Y') }}@endif</p>
@forelse($p->iniciativas as $i)
<div class="card mb-2"><div class="card-body py-2"><strong>{{ $i->descripcion }}</strong> <span class="small">({{ \App\Models\CalidadIniciativa::ESTADOS[$i->estado] ?? $i->estado }}, etapa {{ $i->etapa }}: {{ \App\Models\CalidadIniciativa::ETAPAS[$i->etapa] ?? '' }})</span>
<dl class="row small mb-0 mt-1">
@if($i->objetivo)<dt class="col-sm-3">Objetivo</dt><dd class="col-sm-9">{{ $i->objetivo }}</dd>@endif
@if($i->subcaracteristica || $i->metrica)<dt class="col-sm-3">Subcaracterística y métrica</dt><dd class="col-sm-9">{{ $i->subcaracteristica }} — {{ $i->metrica }}@if($i->umbral_exitoso || $i->umbral_aceptable || $i->umbral_insuficiente) · umbrales: exitoso «{{ $i->umbral_exitoso }}», aceptable «{{ $i->umbral_aceptable }}», insuficiente «{{ $i->umbral_insuficiente }}»@endif</dd>@endif
<dt class="col-sm-3">Responsable y plazo</dt><dd class="col-sm-9">{{ $i->responsable ?: '—' }} · {{ $i->plazo?->format('d-m-Y') ?? 'sin plazo' }}@if($i->costo_estimado !== null) · costo estimado ${{ number_format($i->costo_estimado, 0, ',', '.') }}@endif</dd>
@if($i->riesgos)<dt class="col-sm-3">Riesgos</dt><dd class="col-sm-9">{{ $i->riesgos }}</dd>@endif
@if($i->resultado)<dt class="col-sm-3">Resultado y análisis</dt><dd class="col-sm-9">{{ $i->resultado }}</dd>@endif
</dl></div></div>
@empty<p class="small text-muted">Sin iniciativas registradas.</p>@endforelse
@endforeach
@endsection
