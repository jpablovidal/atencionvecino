{{-- Respuestas a los campos propios del trámite (tal como se enviaron). Variables: $datos (lista guardada en la solicitud) --}}
@if(! empty($datos))
<dl class="row mb-2">@foreach($datos as $d)<dt class="col-sm-4">{{ $d['etiqueta'] }}</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $d['texto'] }}@if(($d['tipo'] ?? '') === 'calculo') <span class="small text-muted">(calculado)</span>@endif</dd>@endforeach</dl>
@endif
