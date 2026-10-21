<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $titulo ?? 'I. Municipalidad de Santo Domingo' }}</title>
</head>
<body style="margin:0;padding:0;background:#f0f1f3;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f0f1f3;">
<tr><td align="center" style="padding:20px 10px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #d9dbde;font-family:Arial,Helvetica,sans-serif;color:#333333;">
<tr><td align="center" style="background:#e2e3e5;padding:18px 20px;border-bottom:4px solid #0b6cb5;">
<img src="{{ asset('assets/img/logo-santo-domingo.png') }}" alt="I. Municipalidad de Santo Domingo" width="46" height="72" style="display:block;border:0;height:72px;width:auto;">
</td></tr>
<tr><td style="padding:28px 24px 8px 24px;font-size:15px;line-height:22px;">
<p style="margin:0 0 16px 0;font-weight:bold;font-size:16px;color:#333333;">{{ $saludo ?? 'Estimado(a) vecino(a)' }}</p>
@yield('contenido')
</td></tr>
@hasSection('boton')
<tr><td align="center" style="padding:12px 24px 24px 24px;">
<a href="{{ $enlace }}" style="display:inline-block;background:#0b5aa8;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 30px;border-radius:3px;">@yield('boton')</a>
</td></tr>
@endif
<tr><td align="center" style="padding:8px 24px 24px 24px;font-size:14px;line-height:21px;color:#333333;">
Atentamente,<br><strong>Ilustre Municipalidad de Santo Domingo</strong>
</td></tr>
<tr><td style="padding:0 24px 22px 24px;font-size:12px;line-height:18px;color:#555555;">
{{ $nota ?? 'Este correo no contiene documentos ni datos personales.' }}
</td></tr>
<tr><td align="center" style="background:#e2e3e5;padding:12px 20px;font-size:11px;line-height:16px;color:#555555;">
Este correo ha sido enviado de manera automática, favor no responda a este remitente.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
