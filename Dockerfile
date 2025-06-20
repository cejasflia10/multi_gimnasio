FROM php:8.1-apache

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Habilitar módulos de Apache si es necesario
RUN a2enmod rewrite

# Copiar el código fuente al contenedor
COPY . /var/www/html/

# Cambiar permisos si es necesario (opcional)
RUN chown -R www-data:www-data /var/www/html

# Puerto expuesto por Apache
EXPOSE 80
