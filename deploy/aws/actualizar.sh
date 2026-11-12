#!/usr/bin/env bash
# Trae lo último de GitHub a la EC2 (equivale a "desplegar").
# Uso:  sudo bash /var/www/html/deploy/aws/actualizar.sh
set -euo pipefail
WEB=/var/www/html
git config --global --add safe.directory "$WEB"
git -C "$WEB" fetch --all -q
git -C "$WEB" reset --hard origin/main -q   # los archivos no versionados (subidas del panel, conexion.php) se conservan
chown -R apache:apache "$WEB"
systemctl reload httpd php-fpm
echo "Actualizado."
