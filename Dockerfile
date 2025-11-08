# --- Servicio WEB: PHP/Apache ---
FROM php:8.1-apache

# Extensiones necesarias
RUN apt-get update && apt-get install -y \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    libzip-dev zip unzip git \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd mysqli pdo pdo_mysql zip \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Config PHP (uploads / tiempos)
COPY php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# App
COPY . /var/www/html/

# Permisos razonables
RUN chown -R www-data:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} \; \
 && find /var/www/html -type f -exec chmod 644 {} \;

# Script de arranque: crea carpetas (si no existen) y lanza Apache en foreground
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80
CMD ["start.sh"]
