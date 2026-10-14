<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><title>Constancia {{ $m->codigo }}</title>
<style>body{font-family:"DejaVu Sans",sans-serif;font-size:10pt;color:#111}h1{font-size:16pt;color:#0a2e4d;margin:0}h2{font-size:11pt;color:#0a2e4d;border-bottom:1px solid #999;margin-top:14pt}table{border-collapse:collapse;width:100%}td,th{border:1px solid #888;padding:3pt 4pt;vertical-align:top;font-size:8.5pt}th{background:#eef4f9;text-align:left}.h{font-family:monospace;font-size:7pt;word-break:break-all}.aviso{border:1px solid #8a5a00;background:#fff4d6;padding:5pt;font-size:8.5pt}</style></head><body>
<h1>Constancia de registro de microforma</h1>
<p><strong>{{ $m->codigo }}</strong> · {{ $m->titulo }}</p>
<p class="aviso">Esta constancia da cuenta de lo registrado en el sistema de archivo municipal (archivos, huellas SHA-256 y cadena de custodia). <strong>No reemplaza el certificado del ministro de fe</strong> ni constituye por sí sola la certificación legal de la microforma.</p>
<h2>Ficha</h2>
<table><tr><th>Estado</th><td>{{ \App\Models\Microforma::ESTADOS[$m->estado] ?? $m->estado }}</td><th>Soporte</th><td>{{ \App\Models\Microforma::SOPORTES[$m->tipo_soporte] ?? $m->tipo_soporte }}</td></tr>
<tr><th>Unidad productora</th><td>{{ $m->unidad?->nombre ?? '—' }}</td><th>Serie</th><td>{{ $m->serie ?? '—' }}</td></tr>
<tr><th>Extremos</th><td>{{ $m->fecha_desde?->format('d-m-Y') ?? '—' }} a {{ $m->fecha_hasta?->format('d-m-Y') ?? '—' }}</td><th>Documentos / imágenes</th><td>{{ $m->n_documentos }} / {{ $m->n_imagenes }}</td></tr>
<tr><th>Ubicación</th><td>{{ $m->ubicacion ?? '—' }}</td><th>Registrada por</th><td>{{ $m->creador?->nombreCompleto() }} ({{ $m->created_at?->format('d-m-Y H:i') }})</td></tr>
@if($m->estado==='certificada')<tr><th>Acta</th><td>{{ $m->acta_numero }}</td><th>Certificada por</th><td>{{ $m->certificador?->nombreCompleto() }} ({{ $m->certificada_en?->format('d-m-Y H:i') }})</td></tr>
<tr><th>Hash del conjunto</th><td colspan="3" class="h">{{ $m->sha256_conjunto }}</td></tr>@endif</table>
<h2>Archivos maestros ({{ $archivos->count() }})</h2>
<table><thead><tr><th>N.º</th><th>Archivo</th><th>Tamaño</th><th>SHA-256</th></tr></thead><tbody>@foreach($archivos as $a)<tr><td>{{ $a->pivot->orden }}</td><td>{{ $a->nombre_original }}</td><td>{{ number_format($a->tamano/1024,0,',','.') }} KB</td><td class="h">{{ $a->sha256 }}</td></tr>@endforeach</tbody></table>
@if($acta)<p>Acta de certificación: {{ $acta->nombre_original }} — <span class="h">{{ $acta->sha256 }}</span></p>@endif
<h2>Índice de documentos ({{ $m->items->count() }})</h2>
<table><thead><tr><th>Referencia</th><th>Descripción</th><th>Fecha</th><th>Fojas</th><th>Posición</th></tr></thead><tbody>@foreach($m->items as $i)<tr><td>{{ $i->referencia }}</td><td>{{ $i->descripcion }}</td><td>{{ $i->fecha_documento?->format('d-m-Y') }}</td><td>{{ $i->fojas }}</td><td>{{ $i->posicion }}</td></tr>@endforeach</tbody></table>
<h2>Cadena de custodia</h2>
<table><thead><tr><th>Fecha</th><th>Evento</th><th>Persona</th></tr></thead><tbody>@foreach($m->eventos as $e)<tr><td>{{ $e->created_at?->format('d-m-Y H:i') }}</td><td>{{ str_replace('_',' ',$e->tipo) }}</td><td>{{ $e->usuario?->nombreCompleto() }}</td></tr>@endforeach</tbody></table>
<p style="font-size:8pt;color:#555">Emitida el {{ now()->format('d-m-Y H:i') }}.</p>
</body></html>
