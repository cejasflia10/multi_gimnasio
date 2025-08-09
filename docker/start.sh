#!/usr/bin/env sh
set -e

UPLOAD_DIR="/var/www/html/multi_gimnasio/comprobantes"

# Asegurar que exista el directorio (si el Disk está montado, solo asegura permisos)
mkdir -p "$UPLOAD_DIR"
chown -R www-data:www-data "$UPLOAD_DIR"

# Iniciar Apache en primer plano (requerido por Render)
exec apache2-foreground
