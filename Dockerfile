FROM php:8.2-apache

# Instala extensiones necesarias para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia todos los archivos del proyecto al directorio público de Apache
COPY . /var/www/html/

# Permisos y habilita mod_rewrite para URLs amigables
RUN chown -R www-data:www-data /var/www/html && a2enmod rewrite

# Expone el puerto 80 para Render
EXPOSE 80
