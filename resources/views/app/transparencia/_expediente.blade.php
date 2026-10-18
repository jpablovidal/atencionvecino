@php $situ = ['vencido'=>['Plazo vencido','vencido'],'por_vencer'=>['Por vencer','por_vencer'],'en_plazo'=>['En plazo','en_plazo']]; @endphp
<section class="card border-primary mb-3" aria-labelledby="tp-t"><div class="card-body">
<h2 class="h5" id="tp-t">Transparencia pasiva (Ley 20.285)</h2>
<dl class="row mb-2">
 <dt class="col-md-3">Plazo de respuesta</dt><dd class="col-md-9">Vence el <strong>{{ $t['vence']->format('d-m-Y') }}</strong> ({{ $t['habiles'] ? 'días hábiles' : 'días corridos' }}) @if($t['terminada'])<span class="plazo plazo-cumplido">Terminada</span>@elseif($t['s']->estado==='devuelta')<span class="plazo plazo-suspendido">Suspendido (devuelta)</span>@else<span class="plazo plazo-{{ $situ[$t['situacion']][1] }}">{{ $situ[$t['situacion']][0] }}</span> <span class="small text-muted">{{ $t['dias'] < 0 ? 'hace '.abs($t['dias']).' d' : ($t['dias'] === 0 ? 'vence hoy' : 'faltan '.$t['dias'].' d') }}</span>@endif</dd>
 <dt class="col-md-3">Prórroga</dt><dd class="col-md-9">@if($t['s']->prorroga_dias)Prorrogada {{ $t['s']->prorroga_dias }} días el {{ $t['s']->prorrogada_en?->setTimezone('America/Santiago')->format('d-m-Y') }}: {{ $t['s']->prorroga_motivo }}@else Sin prórroga @endif</dd>
</dl>
@if($t['puede'] && ! $t['terminada'])
<div class="form-row">
@if(! $t['s']->prorroga_dias && $t['s']->estado !== 'devuelta')
<form method="post" action="{{ route('app.transparencia.prorroga', $t['s']->folio) }}" class="col-lg-6 mb-3" data-confirm="¿Prorrogar el plazo {{ $t['prorrogaDias'] }} días? Solo puede hacerse una vez y antes del vencimiento.">@csrf
 <h3 class="h6">Prorrogar el plazo (+{{ $t['prorrogaDias'] }} días)</h3>
 <label class="sr-only" for="pr-m">Motivo de la prórroga</label><textarea class="form-control mb-2" id="pr-m" name="motivo" rows="3" required minlength="10" maxlength="1000" placeholder="Motivo fundado (se informa al solicitante)"></textarea>
 <button class="btn btn-sm btn-primary">Prorrogar</button></form>
@endif
<form method="post" action="{{ route('app.transparencia.requerir', $t['s']->folio) }}" class="col-lg-6 mb-3">@csrf
 <h3 class="h6">Requerir información a una dirección</h3>
 <label class="sr-only" for="rq-u">Dirección</label><select class="form-control mb-2" id="rq-u" name="unidad_id" required><option value="">Elija la dirección…</option>@foreach($t['unidades'] as $un)<option value="{{ $un->id }}">{{ $un->nombre }}</option>@endforeach</select>
 <label class="sr-only" for="rq-i">Instrucción</label><textarea class="form-control mb-2" id="rq-i" name="instruccion" rows="3" required minlength="10" maxlength="2000" placeholder="Qué información debe entregar la dirección"></textarea>
 <label for="rq-d" class="small mb-0">Plazo (días)</label> <input class="form-control form-control-sm d-inline-block w-auto" type="number" id="rq-d" name="plazo_dias" min="1" max="30" value="{{ $t['reqDias'] }}">
 <button class="btn btn-sm btn-primary">Enviar requerimiento</button></form>
</div>
<details class="mb-3"><summary class="h6 mb-0">Declarar incompetencia y derivar a otro órgano</summary>
<form method="post" action="{{ route('app.transparencia.derivar_organo', $t['s']->folio) }}" class="mt-2" data-confirm="Se registrará la derivación y se CERRARÁ el expediente. ¿Continuar?">@csrf
 <div class="form-row"><div class="form-group col-md-6"><label for="do-o">Órgano competente</label><input class="form-control" id="do-o" name="organo" required maxlength="190"></div>
 <div class="form-group col-md-3"><label for="do-f">Fecha de envío</label><input class="form-control" type="date" id="do-f" name="fecha" required value="{{ now('America/Santiago')->format('Y-m-d') }}"></div>
 <div class="form-group col-md-3"><label for="do-c">Canal</label><select class="form-control" id="do-c" name="canal" required>@foreach($t['canales'] as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div></div>
 <div class="form-group"><label for="do-m">Motivo</label><textarea class="form-control" id="do-m" name="motivo" rows="2" required minlength="10" maxlength="1000"></textarea></div>
 <div class="form-group"><label for="do-r">N.º de oficio o ID de DocDigital (opcional)</label><input class="form-control" id="do-r" name="referencia" maxlength="120"></div>
 <button class="btn btn-sm btn-outline-danger">Registrar derivación y cerrar</button></form></details>
@endif
@if($t['requerimientos']->isNotEmpty())
<h3 class="h6">Requerimientos a direcciones</h3>
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Requerimientos</caption><thead><tr><th scope="col">Dirección</th><th scope="col">Vence</th><th scope="col">Estado</th><th scope="col">Respuesta</th></tr></thead><tbody>
@foreach($t['requerimientos'] as $q)<tr><td>{{ $q->unidad->nombre }}</td><td>{{ $q->vence_en->setTimezone('America/Santiago')->format('d-m-Y') }}</td><td>@if($q->estado==='respondido')<span class="plazo plazo-cumplido">Respondido</span>@elseif($q->vencido())<span class="plazo plazo-vencido">Vencido</span>@else<span class="plazo plazo-en_plazo">Pendiente</span>@endif</td><td class="small">{{ $q->respuesta ?? '—' }}</td></tr>@endforeach</tbody></table></div>
@endif
@foreach($t['derivaciones'] as $d)<p class="mb-1"><span class="etapa etapa-terminada">Derivada a otro órgano</span> {{ $d->organo }} · {{ \Illuminate\Support\Carbon::parse($d->fecha)->format('d-m-Y') }} · {{ $t['canales'][$d->canal] ?? $d->canal }}{{ $d->referencia ? ' · '.$d->referencia : '' }}</p>@endforeach
</div></section>
