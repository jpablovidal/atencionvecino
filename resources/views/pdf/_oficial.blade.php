{{-- Estilo oficial (decretos alcaldicios): Arial/Helvetica 11 pt justificado, membrete en cada hoja y pie con dirección y número de página. Requiere $membrete (App\Support\Membrete::datos()). --}}
<style>
@page{margin:42mm 21mm 27mm 25mm}
body{font-family:Helvetica,Arial,sans-serif;font-size:11pt;line-height:1.28;color:#000;text-align:justify}
p{margin:0 0 7pt} ol,ul{margin:0 0 7pt;padding-left:7mm} li{margin-bottom:4pt;text-align:justify}
h1,h2,h3{color:#000;text-align:center}
.mb-enc{position:fixed;top:-36mm;left:0;right:0;height:30mm} .mb-enc table,.mb-pie table{width:100%;border-collapse:collapse} .mb-enc td,.mb-pie td{border:0;padding:0;vertical-align:middle}
.mb-logo{height:23mm} .mb-sellos{height:15mm}
.mb-txt{font-family:Helvetica,Arial,sans-serif;font-size:10pt;font-weight:bold;line-height:1.35;text-align:left;padding-left:3mm}
.mb-pie{position:fixed;bottom:-20mm;left:0;right:0;font-family:Times,serif;font-size:8pt;color:#555;line-height:1.3;text-align:left}
.mb-pag{text-align:right;font-size:11pt;color:#000;font-family:Helvetica,Arial,sans-serif;vertical-align:bottom} .mb-pag:after{content:counter(page)}
.folio-of{text-align:right;font-size:9pt;color:#333;margin:0 0 6pt}
</style>
<div class="mb-enc"><table><tr>
<td style="width:24%;white-space:nowrap">@if($membrete['logo'])<img class="mb-logo" src="{{ $membrete['logo'] }}" alt="">@endif</td>
<td class="mb-txt" style="width:42%">{{ $membrete['republica'] }}<br>{{ $membrete['institucion'] }}</td>
<td style="text-align:right">@if($membrete['sellos'])<img class="mb-sellos" src="{{ $membrete['sellos'] }}" alt="">@endif</td></tr></table></div>
<div class="mb-pie"><table><tr><td>{{ $membrete['pie'] }}<br>{{ $membrete['direccion'] }}<br>{{ $membrete['web'] }}</td><td class="mb-pag"></td></tr></table></div>
