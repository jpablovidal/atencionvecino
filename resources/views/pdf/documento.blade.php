<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Documento oficial</title><style>
body{font-family:'DejaVu Sans',sans-serif;font-size:11px;color:#111} h1,h2,h3{color:#006fb3} table{border-collapse:collapse} img{max-width:100%} td,th{border:1px solid #999;padding:3px 5px}
.enc{border-bottom:2px solid #006fb3;margin-bottom:12px;padding-bottom:6px;font-size:10px;color:#333} .hoja{page-break-before:always} .sim{color:#b00020;font-weight:bold}
.k{width:30%;background:#eef4f9;font-weight:bold}
</style></head><body>
<div class="enc">@if(! empty($logoPdf))<img src="{{ $logoPdf }}" alt="" style="height:12mm;vertical-align:middle;margin-right:3mm">@endif<strong>I. Municipalidad de Santo Domingo</strong> — {{ $doc->tipo->nombre }}
@if($doc->numero) · {{ $doc->numeroFormateado() }} @else · BORRADOR v{{ $version }} (sin validez) @endif</div>
{{-- $contenido ya fue sanitizado con HTMLPurifier al guardarse --}}
@if(! empty($sellos['inicio'])){!! $sellos['inicio'] !!}@endif
{!! $contenido !!}
@if(! empty($sellos['final'])){!! $sellos['final'] !!}@endif
@if($copia)
<div class="hoja">
<h2>Hoja de certificado de copia pública</h2>
<table width="100%">
<tr><td class="k">Tipo de documento</td><td>{{ $doc->tipo->nombre }}</td></tr>
<tr><td class="k">Folio</td><td>{{ $doc->numeroFormateado() }} (N° {{ $doc->numero }}, año {{ $doc->anio }})</td></tr>
<tr><td class="k">Fecha de firma</td><td>{{ $doc->fecha_firma?->timezone('America/Santiago')->format('d-m-Y H:i') }} (hora de Chile)</td></tr>
<tr><td class="k">Título</td><td>{{ $doc->titulo }}</td></tr>
<tr><td class="k">Firmantes</td><td>@foreach($doc->pasos->where('tipo','firma') as $p){{ $p->usuario->nombreCompleto() }} ({{ $p->usuario->cargo }})@if($p->firma_tipo==='simple') <em>(firma electrónica simple)</em>@endif @if($p->simulada) <span class="sim">FIRMA SIMULADA – sin validez legal</span>@endif<br>@endforeach</td></tr>
<tr><td class="k">Visaciones</td><td>@forelse($doc->pasos->where('tipo','visacion') as $p){{ $p->usuario->nombreCompleto() }}<br>@empty — @endforelse</td></tr>
<tr><td class="k">SHA-256 (contenido)</td><td style="font-size:8px">{{ $sha }}</td></tr>
<tr><td class="k">Código de validación</td><td><strong>{{ $doc->codigo_validacion }}</strong></td></tr>
<tr><td class="k">Validar en</td><td>{{ $url }}</td></tr>
</table>
<p><img src="{{ $qr }}" width="110" height="110" alt="Código QR de validación"></p>
</div>
@endif
</body></html>
