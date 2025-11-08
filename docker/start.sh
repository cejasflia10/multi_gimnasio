#!/usr/bin/env bash
set -e

# Carpetas de trabajo que tu app pueda necesitar (ajusta si querés)
mkdir -p /var/www/html/tmp \
         /var/www/html/storage \
         /var/www/html/cache

chown -R www-data:www-data /var/www/html/tmp /var/www/html/storage /var/www/html/cache || true

# Mantener headers útiles para OBS browser source / HLS si servís archivos estáticos
a2enmod headers >/dev/null 2>&1 || true

# Inicia Apache en primer plano (lo que espera Render)
exec apache2-foreground
