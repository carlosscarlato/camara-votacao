#!/bin/bash
# deploy.sh — Executar no servidor de produção para atualizar o sistema
# Uso: bash deploy.sh
# Requisito: git configurado e database.php com credenciais de produção

set -e

APP_DIR="/var/www/webvoto"
DB_USER="camara_user"
DB_NAME="camara_votacao"
# Lê a senha do arquivo de credenciais do MySQL (evita senha em texto no script)
DB_PASS=$(php -r "define('DB_HOST',''); define('DB_PORT',''); define('DB_NAME',''); define('DB_USER',''); define('DB_CHARSET',''); require '$APP_DIR/config/database.php'; echo DB_PASS;")

echo "=== Deploy Câmara Votação ==="

echo "[1/4] Atualizando código..."
cd "$APP_DIR"
git pull origin main

echo "[2/4] Aplicando migrações do banco..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < update.sql

echo "[3/4] Ajustando permissões..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod 640 "$APP_DIR/config/database.php"
# Pasta de fotos precisa de escrita pelo Apache
chmod -R 775 "$APP_DIR/public/assets/img/vereadores"

echo "[4/4] Recarregando Nginx e PHP-FPM..."
systemctl reload nginx
systemctl reload php8.2-fpm

echo "=== Deploy concluído ==="
