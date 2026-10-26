# Leeme primero — implementación del SGD (Municipalidad de Santo Domingo)

Este paquete (`sgd-release.zip`) es **único y autocontenido**: trae la aplicación, las dependencias y todos los manuales. Léalo en este orden.

## 1. Instalar o actualizar
1. **`docs/GUIA_INSTALACION.md`** (PDF: `docs/manuales/pdf/implementacion-01-guia-de-instalacion.pdf`): instalación en 2 pasos en cPanel (subir el ZIP y pegar **un** comando), actualización, cuentas de prueba y novedades de cada versión. El comando de instalación y de actualización es el mismo:
   ```bash
   cd ~ && sha256sum sgd-release.zip && unzip -q -o sgd-release.zip && rm -f sgd-release.zip && bash ~/sgd/scripts/instalar-cpanel.sh
   ```
   Compare el SHA-256 que imprime con el que se le informó al entregarle el ZIP.
2. **`docs/INSTALACION_GESTOR.md`**: la misma guía, para consulta rápida.

## 2. Configurar y poner en marcha
- **Manual del administrador** (`docs/manuales/pdf/manual-administrador.pdf`): configuración por pantalla de ClaveÚnica, FirmaGob, Gmail, DocDigital, Casilla Única y PISEE (los secretos se escriben en el servidor y se guardan cifrados); usuarios, roles y permisos, **asignación masiva de personas**, perfil **Administrador Municipal**, **subrogancias**, trámites y formularios, feriados, folios, plantillas, archivo municipal y paso a producción.
- **Seguridad** (`docs/SEGURIDAD.md`, PDF `implementacion-02-seguridad.pdf`) e **incidentes** (`docs/INCIDENTES.md`).

## 3. Saber qué está verificado y qué no
- **`docs/LIMITACIONES.md`** (PDF `implementacion-03-limitaciones.pdf`): todo lo que depende de credenciales o contratos reales y no se pudo probar. Léalo antes de pasar a producción.
- `docs/NORMATIVA.md`, `docs/NORMAS_TECNICAS.md`, `docs/DECISIONES.md`, `docs/PENTEST.md`.

## 4. Capacitar a las personas
Manuales por perfil (PDF en `docs/manuales/pdf/` y en el sistema, menú *Ayuda*): ciudadano, funcionario, secretario, alcalde, administrador, auditor, autoridad, OIRS, transparencia, oficina de partes, pagos, tesorero y público. Los del personal se muestran solo a funcionarios; a los vecinos solo el del ciudadano.

> Todas las integraciones quedan **simuladas** hasta que el municipio ingrese sus credenciales reales (ver *Limitaciones*). Antes de operar con datos reales: ejecutar el paso a producción descrito en el manual del administrador.
