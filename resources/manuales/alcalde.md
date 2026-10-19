# Manual del alcalde / alcaldesa

El sistema le permite **firmar** decretos y otros documentos, **delegar** la firma de ciertos tipos de documento por un período y **supervisar** la actividad. Además puede operar expedientes de su unidad como cualquier funcionario (ver el **Manual del funcionario(a)**).

> Requisitos: su RUN debe estar activo con el rol **alcalde** y estar **habilitado(a) para firmar**. Para firmar necesita su **código OTP de FirmaGob**.

## 1. Firmar documentos

1. En su **Bandeja**, bajo "Mis visaciones y firmas pendientes", abra el documento.
2. Revise el texto, el **PDF de la versión actual** y el historial de versiones (huellas SHA-256).
3. Si todo está correcto, ingrese el **OTP de 6 dígitos** y presione **Firmar**. El OTP no se almacena.
4. Si el documento tenía otras firmas, el sistema avisa al siguiente firmante. Al completar la última, el documento queda **vigente** con número correlativo del tipo y del año, código de validación y copia pública.

Si el documento no corresponde, visaciones previas ya hechas no se pierden: pida al redactor que lo corrija y reinicie el flujo (quien **rechaza** una visación devuelve el documento a borrador).

**Decretos alcaldicios:** el primer firmante debe ser usted **o quien tenga una delegación vigente** para ese tipo de documento. El sistema lo verifica al definir el flujo y nuevamente al firmar.

## 2. Delegaciones de firma

**Solo usted puede crear delegaciones.** El administrador y el auditor solo pueden verlas.

![Bandeja con acciones pendientes](/assets/manual/10-bandeja-firma.png)

### Crear una delegación
1. Menú **Delegaciones de firma**.
2. Elija el **tipo de documento** (solo aparecen los tipos cuya primera firma corresponde al alcalde), el **delegado(a)** (funcionario o secretario habilitado para firmar), el **decreto de referencia** que sustenta la delegación y la **vigencia** (desde / hasta).
3. Presione **Registrar delegación**.

### Revocar
En la tabla, presione **Revocar** en la delegación. Deja de valer de inmediato; los flujos ya iniciados con ese delegado **no podrán firmarse** hasta que usted firme o cree otra delegación.

**Buenas prácticas:** delegue por períodos acotados, cite siempre el decreto vigente, revise periódicamente la tabla y revoque cuando termine el motivo. Cada creación y revocación queda en la bitácora de auditoría.

## 3. Comunicaciones oficiales (DocDigital)

Si el municipio recibe comunicaciones oficiales, la **unidad receptora** (normalmente Secretaría Municipal) las gestiona. Usted puede ser **derivado(a)** para opinar o firmar la respuesta. Verá esos expedientes en "Mis expedientes".

## 4. Supervisión

- **Documentos vigentes**: listado de todo lo firmado, con número, fecha y enlace público de validación.
- Para revisar actividad y seguridad pida al **auditor** o al **administrador** el reporte de la cadena de auditoría.

## 5. Seguridad de su cuenta

- Cierre sesión al terminar; la sesión se cierra tras 30 minutos sin actividad.
- Nunca comparta su OTP ni su ClaveÚnica: **nadie** del municipio o de FirmaGob se lo pedirá.
- Si sospecha uso indebido de su firma, avise de inmediato al administrador para que se registre un incidente.

## 6. Problemas frecuentes

| Problema | Qué hacer |
|---|---|
| No puedo firmar: "No es su turno" | Faltan visaciones previas o firmas anteriores. |
| OTP inválido | Genere uno nuevo; tras 5 intentos fallidos FirmaGob bloquea temporalmente. |
| Mi delegado no puede firmar | Verifique que la delegación esté **activa**, dentro de la **vigencia** y para el **tipo** correcto. |
| Quiero cambiar quién firma | Rechace/devuelva a borrador y que el redactor reinicie el flujo. |

## Panel de Alcaldía
En **Panel de Alcaldía** (y en su Inicio) ve, de un vistazo:
- **Sus firmas y visaciones pendientes**: los documentos que le toca firmar ahora y los que aún esperan a otra persona, con los días de espera (alerta desde 3 días). Haga clic en el documento para abrirlo y firmar.
- **Decretos de pago por firmar** y todos los decretos de pago en visación y firma, con la persona a quien le toca.
- **Quién tiene hoy los documentos** en visación y firma, para detectar cuellos de botella.
- **Solicitudes** de toda la municipalidad: activas, con plazo vencido, por vencer, terminadas, por etapa, por dirección y por mes (cada indicador lleva al seguimiento detallado).
- **Pagos**: decretos por estado, antigüedad de lo por pagar (alerta de 30 días, referencial), lo que falta por egresar y los egresos del mes.
Usted solo consulta los pagos: no crea decretos ni egresos.

## Firmar por lote
En **Mis firmas** puede firmar o visar de una vez varios documentos (o todos los pendientes) en lugar de uno por uno: marque los documentos, elija firma avanzada (código OTP) y presione **Firmar lo seleccionado**. Revise los documentos antes (cada uno tiene su enlace); las firmas por delegación siguen las mismas reglas. El Panel de Alcaldía enlaza directo a esta pantalla.
