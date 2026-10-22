<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Comprobante electrónico de ingreso</title><style>
body{font-family:'DejaVu Sans',sans-serif;font-size:11px} h1{color:#006fb3;font-size:18px} table{border-collapse:collapse;width:100%} td,th{border:1px solid #999;padding:4px 6px}
.k{width:28%;background:#eef4f9;font-weight:bold}
</style></head><body>
@if(! empty($logoPdf))<p><img src="{{ $logoPdf }}" alt="" style="height:14mm"></p>@endif
<h1>Comprobante electrónico de ingreso</h1>
<p>I. Municipalidad de Santo Domingo</p>
<table>
<tr><td class="k">Folio</td><td><strong>{{ $s->folio }}</strong></td></tr>
<tr><td class="k">Fecha y hora de presentación</td><td>{{ $s->created_at->timezone('America/Santiago')->format('d-m-Y H:i:s') }} (hora de Chile) · {{ $s->created_at->copy()->utc()->format('Y-m-d H:i:s') }} UTC</td></tr>
<tr><td class="k">Origen de la presentación</td><td>Formulario web de solicitudes (plataforma SGD)</td></tr>
<tr><td class="k">Órgano ante el cual se tramita</td><td>I. Municipalidad de Santo Domingo @if(config('sgd.institucion_codigo'))(código {{ config('sgd.institucion_codigo') }})@endif</td></tr>
<tr><td class="k">Recepción realizada por</td><td>Plataforma SGD (recepción automática)</td></tr>
<tr><td class="k">Expediente vinculado</td><td>@if($s->expediente){{ $s->expediente->codigo }}@else Se asigna cuando un funcionario(a) toma la solicitud; podrá verlo en el seguimiento.@endif</td></tr>
<tr><td class="k">RUN</td><td>{{ $s->run }}</td></tr>
<tr><td class="k">Solicitante</td><td>{{ $s->nombres }} {{ $s->apellidos }}</td></tr>
<tr><td class="k">Correo</td><td>{{ $s->correo }}</td></tr>
@if($s->en_nombre_de)<tr><td class="k">Presentada a nombre de</td><td>{{ $s->interesado_nombre }} (RUN {{ $s->interesado_run }}) · relación: {{ \App\Models\Solicitud::RELACIONES[$s->relacion] ?? $s->relacion }} · acreditación declarada: {{ $s->acreditacion }}</td></tr>
@endif
<tr><td class="k">Trámite</td><td>{{ $s->tramite->nombre }} @if($s->tramite->codigo_cpat)(CPAT {{ $s->tramite->codigo_cpat }})@endif</td></tr>
<tr><td class="k">Forma de notificación</td><td>{{ str_replace('_',' ',$s->canal_notificacion) }}</td></tr>
<tr><td class="k">Detalle</td><td>{{ $s->detalle }}</td></tr>
@foreach((array) $s->datos as $d)<tr><td class="k">{{ $d['etiqueta'] }}</td><td>{{ $d['texto'] }}@if(($d['tipo'] ?? '') === 'calculo') (calculado)@endif</td></tr>@endforeach
</table>
<h3>Anexos</h3>
<table><tr><th scope="col">Nombre</th><th scope="col">Tamaño (bytes)</th><th scope="col">SHA-256</th></tr>
@forelse($anexos as $a)<tr><td>{{ $a['nombre'] }}</td><td>{{ $a['tamano'] }}</td><td style="font-size:7px">{{ $a['sha256'] }}</td></tr>
@empty<tr><td colspan="3">Sin anexos</td></tr>@endforelse</table>
<p>Código de verificación: <strong>{{ $codigo }}</strong></p>
<p><img src="{{ $qr }}" width="100" height="100" alt="QR"></p>
<p style="font-size:9px">Este comprobante acredita la recepción de la solicitud; no constituye resolución. Verificación: {{ $url }}</p>
</body></html>
