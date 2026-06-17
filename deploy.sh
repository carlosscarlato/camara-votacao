#!/bin/bash
# deploy.sh — Atualização no servidor de produção
set -e

APP_DIR="/var/www/camara-votacao"

echo "=== Deploy WebVoto SaaS ==="

echo "[1/4] Atualizando código..."
cd "$APP_DIR"
git fetch origin main
git reset --hard origin/main

echo "[2/4] Instalando dependências Composer..."
if [ -f composer.phar ]; then
  php composer.phar install --no-dev --optimize-autoloader --no-interaction
elif command -v composer &>/dev/null; then
  composer install --no-dev --optimize-autoloader --no-interaction
fi

echo "[3/4] Ajustando permissões..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
[ -f "$APP_DIR/config/database.php" ] && chmod 640 "$APP_DIR/config/database.php"
mkdir -p "$APP_DIR/public/assets/tenants"
chmod -R 775 "$APP_DIR/public/assets"

echo "[4/4] Recarregando Nginx e PHP-FPM..."
systemctl reload nginx 2>/dev/null || true
systemctl reload php8.2-fpm 2>/dev/null || true

echo "=== Deploy concluído ==="
