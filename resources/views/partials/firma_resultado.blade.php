@php
  $etq = \App\Services\Firma\ArchivoFirmaService::etiqueta($estado);
  $clase = match ($estado) { 'firmada' => 'estado-vigente', 'invalida' => 'estado-fallida', 'firmada_con_cambios', 'no_verificable' => 'estado-pendiente', default => '' };
  $firmas = $det['firmas'] ?? [];
@endphp
<span class="estado {{ $clase }}">{{ $etq }}</span>
@if($firmas)
<details class="d-inline-block align-top ml-1"><summary class="small">Ver firmas ({{ count($firmas) }})</summary>
  <ul class="small mb-2">
  @foreach($firmas as $f)
    <li>
      <strong>{{ $f['tipo'] === 'sello_tiempo' ? 'Sello de tiempo' : ($f['firmante'] ?: 'Firmante no identificado') }}</strong> — {{ $f['formato'] }}
      <ul>
        @if($f['emisor'])<li>Emitido por: {{ $f['emisor'] }}</li>@endif
        @if($f['sujeto'])<li>Certificado: {{ $f['sujeto'] }}</li>@endif
        @if($f['fecha_firma'] || $f['campo_fecha'])<li>Fecha de firma: {{ $f['fecha_firma'] ?? $f['campo_fecha'] }}</li>@endif
        @if($f['valido_desde'])<li>Vigencia del certificado: {{ $f['valido_desde'] }} a {{ $f['valido_hasta'] }}@if($f['certificado_vigente_al_firmar'] === false) <strong>(no estaba vigente al firmar)</strong>@endif</li>@endif
        <li>Integridad: {{ $f['integridad'] === true ? 'el contenido coincide con lo firmado' : ($f['integridad'] === false ? 'NO coincide: documento alterado o firma inválida' : 'no verificable') }}@if($f['algoritmo']) ({{ $f['algoritmo'] }})@endif</li>
        <li>Cobertura: {{ $f['cubre_todo'] ? 'la firma cubre todo el archivo' : 'se agregó contenido después de esta firma' }}</li>
        <li>Confianza del emisor: {{ ['verificada' => 'cadena verificada con las CA configuradas', 'no_verificada' => 'no verificada (no hay CA de confianza configurada)', 'no_confiable' => 'cadena no confiable'][$f['cadena']] ?? $f['cadena'] }}</li>
        @if($f['razon'])<li>Motivo declarado: {{ $f['razon'] }}</li>@endif
        @foreach($f['errores'] as $er)<li class="text-danger">{{ $er }}</li>@endforeach
      </ul>
    </li>
  @endforeach
  </ul>
  @foreach($det['observaciones'] ?? [] as $o)<p class="small text-danger mb-1">{{ $o }}</p>@endforeach
  <p class="small text-muted mb-0">La verificación no revisa revocación del certificado y no determina por sí sola si la firma es «avanzada» (Ley 19.799).</p>
</details>
@endif
