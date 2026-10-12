<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Prueba de firmas</title><style>body{font-family:'DejaVu Sans',sans-serif;font-size:11px;color:#111}h2{color:#006fb3}</style></head><body>
@if(! empty($sellos['inicio'])){!! $sellos['inicio'] !!}@endif
<h2>Documento de prueba (datos ficticios)</h2>
<p>Este PDF solo sirve para revisar el estilo y la posición de los sellos de firma configurados. No tiene validez.</p>
@for($i=0;$i<14;$i++)<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>@endfor
@if(! empty($sellos['final'])){!! $sellos['final'] !!}@endif
</body></html>
