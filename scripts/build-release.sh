#!/usr/bin/env bash
# Genera sgd-release.zip listo para subir a cPanel (sin SSH): incluye vendor/ (--no-dev, autoloader optimizado),
# SIN .env, SIN tests, SIN archivos de desarrollo. Reproducible: mismas fuentes + composer.lock => mismo contenido.
# Uso: scripts/build-release.sh [salida.zip]
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/sgd-release.zip}"
BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT
STAGE="$BUILD/sgd"
mkdir -p "$STAGE"

echo ">> Copiando fuentes..."
cd "$ROOT"
tar --exclude-vcs \
    --exclude='./vendor' --exclude='./node_modules' --exclude='./tests' --exclude='./.env' --exclude='./.env.*' \
    --exclude='./storage/logs/*' --exclude='./storage/framework/*/*' --exclude='./storage/app/sgd_storage' \
    --exclude='./database/*.sqlite' --exclude='./docs/capturas' --exclude='./sgd-release.zip' --exclude='./.phpunit.cache' \
    --exclude='./phpunit.xml' --exclude='./phpstan.neon' --exclude='./.github' --exclude='./scripts/generate-model-docs.php' --exclude='./scripts/a11y-audit.cjs' --exclude='./scripts/qa-crawl.cjs' --exclude='./scripts/qa-tramites.cjs' --exclude='./scripts/qa-flujo.cjs' --exclude='./package.json' --exclude='./vite.config.js' --exclude='./docs/manuales/pdf' \
    --exclude='./.phpunit.result.cache' --exclude='./.editorconfig' --exclude='./.gitattributes' --exclude='./.styleci.yml' \
    -cf - . | tar -xf - -C "$STAGE"
cp .env.example "$STAGE/.env.example"
# la caché de paquetes del equipo de desarrollo menciona proveedores de desarrollo: se regenera en el servidor
rm -f "$STAGE"/bootstrap/cache/*.php

echo ">> Instalando dependencias de producción (composer.lock)..."
cd "$STAGE"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

# descubrimiento de paquetes SOLO de producción (sin dependencias de desarrollo)
APP_ENV=production php artisan package:discover --ansi || true

# Directorios que Laravel necesita escribibles
mkdir -p storage/framework/{cache,sessions,views,htmlpurifier,dompdf} storage/logs bootstrap/cache
for d in storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/htmlpurifier storage/framework/dompdf storage/logs bootstrap/cache; do touch "$d/.gitkeep"; done # el ZIP solo guarda archivos: sin esto las carpetas vacías no existirían
find . -name '.git*' -not -name '.gitkeep' -prune -exec rm -rf {} + 2>/dev/null || true
find . -type d \( -name tests -o -name Tests -o -name docs \) -path './vendor/*' -prune -exec rm -rf {} + 2>/dev/null || true
find vendor -type f \( -name 'phpunit.xml*' -o -name '.phpunit.result.cache' -o -name 'phpstan*.neon' -o -name 'CHANGELOG*' \) -delete 2>/dev/null || true
rm -f .env
# permisos y fechas deterministas
find . -type d -exec chmod 750 {} + ; find . -type f -exec chmod 640 {} +
find . -exec touch -h -d "@$(git -C "$ROOT" log -1 --format=%ct 2>/dev/null || echo 1700000000)" {} + 2>/dev/null || true

echo ">> Comprimiendo..."
rm -f "$OUT"
cd "$BUILD"
find sgd -type f -print0 | LC_ALL=C sort -z | xargs -0 zip -X -q "$OUT"
( cd "$BUILD" && unzip -l "$OUT" | tail -1 )
sha256sum "$OUT" | tee "$OUT.sha256"
echo ">> Listo: $OUT"
