@foreach($filas as $fila)
<table role="presentation" width="100%" style="margin-top:{{ (int) $fila['margen'] }}mm;page-break-inside:avoid;border:0;border-collapse:collapse"><tr>
  <td width="33%" style="border:0;padding:0;vertical-align:top">{!! $fila['celdas']['izquierda'] ?? '' !!}</td>
  <td width="34%" style="border:0;padding:0;vertical-align:top;text-align:center"><div style="display:inline-block">{!! $fila['celdas']['centro'] ?? '' !!}</div></td>
  <td width="33%" style="border:0;padding:0;vertical-align:top;text-align:right"><div style="display:inline-block;text-align:left">{!! $fila['celdas']['derecha'] ?? '' !!}</div></td>
</tr></table>
@endforeach
