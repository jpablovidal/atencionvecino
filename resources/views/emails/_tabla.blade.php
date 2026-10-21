<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #d9dbde;border-collapse:collapse;margin:0 0 18px 0;font-size:14px;line-height:20px;">
@foreach($filas as $etiqueta => $valor)
<tr>
<td width="32%" valign="top" style="background:#f4f7fa;color:#555555;padding:12px 14px;border-bottom:1px solid #e3e5e8;">{{ $etiqueta }}</td>
<td valign="top" style="color:#222222;padding:12px 14px;border-bottom:1px solid #e3e5e8;font-weight:bold;">{{ $valor }}</td>
</tr>
@endforeach
</table>
