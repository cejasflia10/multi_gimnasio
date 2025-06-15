FROM php:8.2-apache

# Instala extensiones necesarias para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copia todos los archivos del proyecto al directorio público de Apache
COPY . /var/www/html/

# Permisos y habilita mod_rewrite para URLs amigables
RUN chown -R www-data:www-data /var/www/html && a2enmod rewrite

# Expone el puerto 80 para Render
=======
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && a2enmod rewrite

>>>>>>> 4e3b6cf95073798889fc8bc14d8e95c68d7aa29e
EXPOSE 80
