{{-- Fila del editor de campos del formulario de un trámite. Variables: $i (índice o __I__), $f (datos), $listas --}}
@php $c = (array) ($f['config'] ?? []); $t = $f['tipo'] ?? 'texto'; @endphp
<div class="campo-fila border rounded p-2 mb-2" data-fila>
  <input type="hidden" name="campos[{{ $i }}][id]" value="{{ $f['id'] ?? '' }}">
  <input type="hidden" name="campos[{{ $i }}][eliminar]" value="0" data-eliminar>
  <div class="form-row">
    <div class="form-group col-md-3 mb-1"><label for="c-e-{{ $i }}">Nombre del campo</label><input class="form-control" id="c-e-{{ $i }}" name="campos[{{ $i }}][etiqueta]" maxlength="190" value="{{ $f['etiqueta'] ?? '' }}" data-etiqueta></div>
    <div class="form-group col-md-4 mb-1"><label for="c-t-{{ $i }}">Tipo</label><select class="form-control" id="c-t-{{ $i }}" name="campos[{{ $i }}][tipo]" data-tipo-campo>@foreach(\App\Models\TramiteCampo::TIPOS as $k => $n)<option value="{{ $k }}" @selected($t === $k)>{{ $n }}</option>@endforeach</select></div>
    <div class="form-group col-md-3 mb-1"><label for="c-k-{{ $i }}">Clave (para fórmulas)</label><input class="form-control" id="c-k-{{ $i }}" name="campos[{{ $i }}][clave]" maxlength="30" pattern="[a-z][a-z0-9_]{0,29}" placeholder="se genera sola" value="{{ $f['clave'] ?? '' }}" data-clave @if(! empty($f['id'])) data-manual="1" @endif></div>
    <div class="form-group col-md-2 mb-1 pt-4" data-tipos="texto parrafo numero fecha lista multiple si_no"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="c-o-{{ $i }}" name="campos[{{ $i }}][obligatorio]" value="1" @checked(! empty($f['obligatorio']))><label class="custom-control-label" for="c-o-{{ $i }}">Obligatorio</label></div></div>
  </div>
  <div class="form-group mb-1" data-tipos="texto parrafo numero fecha lista multiple si_no calculo"><label for="c-a-{{ $i }}">Ayuda para quien completa (opcional)</label><input class="form-control" id="c-a-{{ $i }}" name="campos[{{ $i }}][ayuda]" maxlength="255" value="{{ $f['ayuda'] ?? '' }}"></div>

  <div class="form-row" data-tipos="texto parrafo"><div class="form-group col-md-3 mb-1"><label for="c-ml-{{ $i }}">Largo máximo (caracteres)</label><input class="form-control" id="c-ml-{{ $i }}" type="number" min="1" max="2000" name="campos[{{ $i }}][maxlen]" value="{{ $c['maxlen'] ?? '' }}"></div></div>

  <div class="form-row" data-tipos="numero"><div class="form-group col-md-3 mb-1"><label for="c-mn-{{ $i }}">Mínimo</label><input class="form-control" id="c-mn-{{ $i }}" name="campos[{{ $i }}][min]" inputmode="decimal" value="{{ $c['min'] ?? '' }}"></div>
    <div class="form-group col-md-3 mb-1"><label for="c-mx-{{ $i }}">Máximo</label><input class="form-control" id="c-mx-{{ $i }}" name="campos[{{ $i }}][max]" inputmode="decimal" value="{{ $c['max'] ?? '' }}"></div></div>
  <div class="form-row" data-tipos="numero calculo"><div class="form-group col-md-3 mb-1"><label for="c-dc-{{ $i }}">Decimales</label><input class="form-control" id="c-dc-{{ $i }}" type="number" min="0" max="6" name="campos[{{ $i }}][decimales]" value="{{ $c['decimales'] ?? '' }}"></div>
    <div class="form-group col-md-3 mb-1"><label for="c-pf-{{ $i }}">Antes del número (p. ej. $)</label><input class="form-control" id="c-pf-{{ $i }}" maxlength="12" name="campos[{{ $i }}][prefijo]" value="{{ $c['prefijo'] ?? '' }}"></div>
    <div class="form-group col-md-3 mb-1"><label for="c-sf-{{ $i }}">Después del número (p. ej. m²)</label><input class="form-control" id="c-sf-{{ $i }}" maxlength="12" name="campos[{{ $i }}][sufijo]" value="{{ $c['sufijo'] ?? '' }}"></div></div>

  <div class="form-row" data-tipos="fecha"><div class="form-group col-md-4 mb-1"><label for="c-fn-{{ $i }}">Desde (opcional)</label><input class="form-control" id="c-fn-{{ $i }}" name="campos[{{ $i }}][fecha_min]" maxlength="10" placeholder="hoy o AAAA-MM-DD" value="{{ $c['fecha_min'] ?? '' }}"></div>
    <div class="form-group col-md-4 mb-1"><label for="c-fx-{{ $i }}">Hasta (opcional)</label><input class="form-control" id="c-fx-{{ $i }}" name="campos[{{ $i }}][fecha_max]" maxlength="10" placeholder="hoy o AAAA-MM-DD" value="{{ $c['fecha_max'] ?? '' }}"></div></div>

  <div data-tipos="lista multiple">
    <div class="form-row"><div class="form-group col-md-6 mb-1"><label for="c-l-{{ $i }}">Usar un listado guardado</label><select class="form-control" id="c-l-{{ $i }}" name="campos[{{ $i }}][lista_id]"><option value="">— Escribir las opciones aquí —</option>@foreach($listas as $l)<option value="{{ $l->id }}" @selected((int) ($c['lista_id'] ?? 0) === $l->id)>{{ $l->nombre }} ({{ $l->items_count }})</option>@endforeach</select></div>
      <div class="form-group col-md-3 mb-1" data-tipos="multiple"><label for="c-me-{{ $i }}">Máximo que puede elegir</label><input class="form-control" id="c-me-{{ $i }}" type="number" min="1" max="50" name="campos[{{ $i }}][max_elegidas]" value="{{ $c['max_elegidas'] ?? '' }}"></div></div>
    <div class="form-group mb-1"><label for="c-op-{{ $i }}">…o escriba las opciones (una por línea; <code>valor;texto</code> si el valor es un número para calcular)</label><textarea class="form-control" id="c-op-{{ $i }}" rows="4" name="campos[{{ $i }}][opciones]">{{ $f['opciones_texto'] ?? collect($c['opciones'] ?? [])->map(fn ($o) => $o['valor'].';'.$o['etiqueta'])->implode("\n") }}</textarea></div>
  </div>

  <div data-tipos="calculo"><div class="form-group mb-1"><label for="c-f-{{ $i }}">Fórmula</label><textarea class="form-control text-monospace" id="c-f-{{ $i }}" rows="2" maxlength="400" name="campos[{{ $i }}][formula]" aria-describedby="c-fa-{{ $i }}" data-formula>{{ $c['formula'] ?? '' }}</textarea>
    <small id="c-fa-{{ $i }}" class="form-text text-muted">Ejemplo: <code>superficie * valor_m2 * 0.02</code>. Use los campos por su clave, operadores y funciones (ver la guía de abajo). <span data-variables></span></small></div></div>

  <div class="mt-1"><button type="button" class="btn btn-sm btn-outline-secondary" data-subir>Subir<span class="sr-only"> el campo</span></button> <button type="button" class="btn btn-sm btn-outline-secondary" data-bajar>Bajar<span class="sr-only"> el campo</span></button> <button type="button" class="btn btn-sm btn-outline-danger" data-quitar>Quitar<span class="sr-only"> el campo</span></button></div>
</div>
