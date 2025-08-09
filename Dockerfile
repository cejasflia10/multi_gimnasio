FROM php:8.1-apache

# Paquetes necesarios (GD con freetype/jpeg, zip, etc.)
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql zip \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# PHP config para uploads (se copia como ini suelto)
COPY php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Script de arranque: asegura carpeta de comprobantes y permisos en cada boot
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Copiar app
COPY . /var/www/html/

# Propietario por defecto (el Disk montado igual se repropietariza en start.sh)
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html

EXPOSE 80

# Arranque (Render usa este CMD)
CMD ["start.sh"]
