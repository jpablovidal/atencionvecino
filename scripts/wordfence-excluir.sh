#!/usr/bin/env bash
# Evita que el firewall de Wordfence (plugin de WordPress que el servidor carga con «auto_prepend_file») bloquee este sistema con el mensaje
# «A potentially unsafe operation has been detected in your request to this site» (HTTP 403). El sistema NO es WordPress y tiene sus propias
# defensas (validación de archivos, CSP, auditoría, límites). Solo afecta a ESTE sitio: crea public/.user.ini con «auto_prepend_file =» vacío.
# Uso:  bash ~/sgd/scripts/wordfence-excluir.sh            (aplicar)
#       bash ~/sgd/scripts/wordfence-excluir.sh --revertir (volver a cargar Wordfence en este sitio)
# PHP-FPM relee .user.ini cada 5 minutos (user_ini.cache_ttl): espere unos minutos o reinicie PHP desde cPanel → MultiPHP Manager.
set -euo pipefail
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ARCH="$RAIZ/public/.user.ini"
if [ "${1:-}" = "--revertir" ]; then
  rm -f "$ARCH"; echo "Listo: este sitio vuelve a cargar lo que defina el servidor (p. ej. Wordfence)."; exit 0
fi
cat > "$ARCH" <<'EOF'
; SGD: no cargar el firewall de Wordfence (WordPress) en esta aplicación (evita bloqueos 403 falsos al enviar solicitudes con archivos)
auto_prepend_file =
EOF
chmod 644 "$ARCH"
echo "Listo: $ARCH creado. Espere unos 5 minutos (o reinicie PHP en cPanel → MultiPHP Manager) y pruebe de nuevo."
echo "Para deshacerlo: bash $RAIZ/scripts/wordfence-excluir.sh --revertir"
