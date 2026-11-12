#!/usr/bin/env bash
# Instala todo el sitio en una EC2 con Amazon Linux 2023:
#   Apache + PHP 8.2 (FPM) + MariaDB local + el código desde GitHub.
#
# Uso (como root, dentro de la EC2):
#   sudo CODIGO_RECUPERACION='algo-largo-1' CODIGO_REGISTRO='algo-largo-2' bash setup-ec2.sh
#
# Opcionales:
#   DB_PASS   contraseña de la base (si no se da, se genera una y se guarda en /root/ddp-credenciales.txt)
#   REPO      URL del repositorio  (por defecto el de GitHub del proyecto)
#   RAMA      rama a desplegar     (por defecto main)
#   /tmp/revista_digital.sql  si existe, se importa (tu base con datos); si no, se carga database/schema.sql
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Ejecuta con sudo."; exit 1; }
: "${CODIGO_RECUPERACION:?Define CODIGO_RECUPERACION}"
: "${CODIGO_REGISTRO:?Define CODIGO_REGISTRO (usa '-' si no quieres registro público)}"
[ "$CODIGO_REGISTRO" = "-" ] && CODIGO_REGISTRO=""

REPO="${REPO:-https://github.com/JosuheM/reportaje-aws.git}"
RAMA="${RAMA:-main}"
WEB=/var/www/html
DB_NAME=revista_digital
DB_USER=ddp
DB_PASS="${DB_PASS:-$(openssl rand -hex 16)}"

echo ">> Memoria extra (swap 1 GB) para instancias de 1 GB de RAM"
if ! swapon --show | grep -q .; then
  dd if=/dev/zero of=/swapfile bs=1M count=1024 status=none
  chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo ">> Instalando paquetes"
dnf -y -q update
dnf -y -q install httpd git mariadb105-server \
  php8.2 php8.2-fpm php8.2-mysqlnd php8.2-mbstring php8.2-gd php8.2-xml php8.2-opcache

echo ">> Configurando PHP (subidas de hasta 25 MB para PDF)"
cat > /etc/php.d/99-ddp.ini <<PHP
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
expose_php = Off
display_errors = Off
PHP

echo ">> Base de datos"
systemctl enable --now mariadb
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

echo ">> Código desde GitHub"
if [ -d "$WEB/.git" ]; then
  git -C "$WEB" fetch --all -q && git -C "$WEB" reset --hard "origin/$RAMA" -q
else
  rm -rf "${WEB:?}"/* 
  git clone -q --branch "$RAMA" "$REPO" "$WEB"
fi
git config --global --add safe.directory "$WEB"

TABLAS=$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")
if [ "$TABLAS" -eq 0 ]; then
  if [ -f /tmp/revista_digital.sql ]; then
    echo ">> Importando /tmp/revista_digital.sql"
    mysql "$DB_NAME" < /tmp/revista_digital.sql
  else
    echo ">> Importando database/schema.sql (base vacía: tendrás que crear el primer usuario, ver README)"
    mysql "$DB_NAME" < "$WEB/database/schema.sql"
  fi
else
  echo ">> La base ya tiene tablas: no se importa nada"
fi

echo ">> Configuración de la app (variables de entorno de Apache, solo legibles por root)"
cp "$WEB/admin/config/conexion.example.php" "$WEB/admin/config/conexion.php"
cat > /etc/httpd/conf.d/ddp.conf <<CONF
SetEnv DB_HOST localhost
SetEnv DB_NAME $DB_NAME
SetEnv DB_USER $DB_USER
SetEnv DB_PASS $DB_PASS
SetEnv DB_SSL 0
SetEnv CODIGO_RECUPERACION $CODIGO_RECUPERACION
SetEnv CODIGO_REGISTRO "$CODIGO_REGISTRO"

<Directory "$WEB">
    AllowOverride All
    Require all granted
</Directory>
# No servir ni el repositorio ni el SQL ni los .md
<DirectoryMatch "/\.git">
    Require all denied
</DirectoryMatch>
<FilesMatch "\.(sql|md|sh|yml)$">
    Require all denied
</FilesMatch>
ServerTokens Prod
ServerSignature Off
CONF
chmod 600 /etc/httpd/conf.d/ddp.conf

echo ">> Permisos (Apache debe poder escribir las subidas)"
chown -R apache:apache "$WEB"
chmod -R u+rwX,go+rX "$WEB"

echo ">> Arrancando servicios"
systemctl enable --now php-fpm httpd
systemctl restart php-fpm httpd

cat > /root/ddp-credenciales.txt <<TXT
DB_USER=$DB_USER
DB_PASS=$DB_PASS
CODIGO_RECUPERACION=$CODIGO_RECUPERACION
CODIGO_REGISTRO=$CODIGO_REGISTRO
TXT
chmod 600 /root/ddp-credenciales.txt

IP=$(curl -s -m 3 -H "X-aws-ec2-metadata-token: $(curl -s -m 3 -X PUT http://169.254.169.254/latest/api/token -H 'X-aws-ec2-metadata-token-ttl-seconds: 60')" http://169.254.169.254/latest/meta-data/public-ipv4 || true)
echo
echo "LISTO. Abre: http://${IP:-<IP-PUBLICA>}/"
echo "Credenciales guardadas en /root/ddp-credenciales.txt"
