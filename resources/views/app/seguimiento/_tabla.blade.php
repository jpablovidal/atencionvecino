@php $plazoTxt = ['vencido'=>'Vencido','por_vencer'=>'Por vencer','en_plazo'=>'En plazo','cumplido'=>'Cumplido en plazo','fuera_de_plazo'=>'Terminada fuera de plazo','suspendido'=>'Suspendido (devuelta)']; @endphp
<div class="table-responsive"><table class="table table-sm"><caption class="sr-only">Solicitudes</caption>
<thead><tr><th scope="col">Folio</th><th scope="col">Solicitante</th><th scope="col">Trámite</th><th scope="col">Dirección</th><th scope="col">Ingreso</th><th scope="col">Etapa y estado</th><th scope="col">Quién la tiene</th><th scope="col">Plazo</th></tr></thead><tbody>
@forelse($filas as $x)
<tr>
  <td class="text-nowrap"><a href="{{ ($modo ?? '') === 'transparencia' ? ($x['s']->expediente_id ? route('app.expediente', $x['s']->expediente_id) : route('app.solicitud', $x['folio'])) : route('app.seguimiento.detalle', $x['folio']) }}">{{ $x['folio'] }}</a></td>
  <td>{{ $x['solicitante'] }}<br><small class="text-muted">{{ $x['run'] }}</small></td>
  <td>{{ $x['tramite'] }}</td><td>{{ $x['direccion'] }}</td>
  <td class="text-nowrap">{{ $x['ingreso']->format('d-m-Y') }}</td>
  <td><span class="etapa etapa-{{ $x['etapa'] }}">{{ $x['etapa_txt'] }}</span><br><small class="text-muted">{{ $x['terminada'] ? 'Terminó el '.$x['termino']->format('d-m-Y') : 'En curso' }}</small></td>
  <td>@if($x['terminada'])<em>Terminada</em>@else{{ $x['tiene'] ?? 'Sin asignar' }}@endif @if($x['responsable'] && !$x['terminada'] && $x['tiene'] !== $x['responsable'])<br><small class="text-muted">Responsable: {{ $x['responsable'] }}</small>@endif</td>
  <td><span class="plazo plazo-{{ $x['plazo'] }}">{{ $plazoTxt[$x['plazo']] }}</span><br><small class="text-muted">Vence {{ $x['vence']->format('d-m-Y') }}@if($x['dias'] !== null) · {{ $x['dias'] < 0 ? 'hace '.abs($x['dias']).' d' : ($x['dias'] === 0 ? 'hoy' : 'en '.$x['dias'].' d') }}@endif</small>@if($x['vence_solicitante'])<br><small class="text-muted">Solicitante responde hasta el {{ $x['vence_solicitante']->setTimezone('America/Santiago')->format('d-m-Y') }}</small>@endif</td>
</tr>
@empty<tr><td colspan="8">No hay solicitudes para esta búsqueda.</td></tr>@endforelse
</tbody></table></div>
@if($pag)<div class="d-flex justify-content-between align-items-center flex-wrap"><p class="small mb-2">{{ $pag->total() }} solicitud(es)</p>{{ $pag->links() }}</div>@endif
