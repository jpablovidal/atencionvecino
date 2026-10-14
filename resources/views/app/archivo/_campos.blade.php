<div class="form-group"><label for="titulo">Título de la microforma</label><input class="form-control" id="titulo" name="titulo" required minlength="5" maxlength="190" value="{{ old('titulo',$m->titulo ?? '') }}"></div>
<div class="form-group"><label for="descripcion">Descripción (alcance, contenido, estado de conservación)</label><textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="2000">{{ old('descripcion',$m->descripcion ?? '') }}</textarea></div>
<div class="form-row">
  <div class="form-group col-md-6"><label for="tipo_soporte">Tipo de soporte</label><select class="form-control" id="tipo_soporte" name="tipo_soporte" required>@foreach(\App\Models\Microforma::SOPORTES as $k=>$v)<option value="{{ $k }}" @selected(old('tipo_soporte',$m->tipo_soporte ?? 'digital')===$k)>{{ $v }}</option>@endforeach</select></div>
  <div class="form-group col-md-6"><label for="unidad_id">Unidad productora de los documentos</label><select class="form-control" id="unidad_id" name="unidad_id"><option value="">— sin unidad —</option>@foreach($unidades as $un)<option value="{{ $un->id }}" @selected((int) old('unidad_id',$m->unidad_id ?? 0)===$un->id)>{{ $un->nombre }}</option>@endforeach</select></div>
</div>
<div class="form-row">
  <div class="form-group col-md-6"><label for="serie">Serie documental</label><input class="form-control" id="serie" name="serie" maxlength="120" value="{{ old('serie',$m->serie ?? '') }}" placeholder="Ej.: Decretos alcaldicios"></div>
  <div class="form-group col-md-6"><label for="ubicacion">Ubicación física del soporte</label><input class="form-control" id="ubicacion" name="ubicacion" maxlength="190" value="{{ old('ubicacion',$m->ubicacion ?? '') }}" placeholder="Bodega, estante, caja"></div>
</div>
<div class="form-row">
  <div class="form-group col-md-3"><label for="fecha_desde">Fecha inicial</label><input class="form-control" type="date" id="fecha_desde" name="fecha_desde" value="{{ old('fecha_desde',optional($m?->fecha_desde)->format('Y-m-d')) }}"></div>
  <div class="form-group col-md-3"><label for="fecha_hasta">Fecha final</label><input class="form-control" type="date" id="fecha_hasta" name="fecha_hasta" value="{{ old('fecha_hasta',optional($m?->fecha_hasta)->format('Y-m-d')) }}"></div>
  <div class="form-group col-md-3"><label for="n_documentos">Cantidad de documentos</label><input class="form-control" type="number" min="0" id="n_documentos" name="n_documentos" value="{{ old('n_documentos',$m->n_documentos ?? 0) }}"></div>
  <div class="form-group col-md-3"><label for="n_imagenes">Cantidad de imágenes / fotogramas</label><input class="form-control" type="number" min="0" id="n_imagenes" name="n_imagenes" value="{{ old('n_imagenes',$m->n_imagenes ?? 0) }}"></div>
</div>
<p class="small text-muted">Los textos se guardan como texto plano (sin formato ni enlaces).</p>
