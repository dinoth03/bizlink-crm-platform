FROM php:8.2-apache

# Enable MySQL support for PHP
RUN docker-php-ext-install mysqli

# Cloud Run expects the container to listen on PORT (use 8080 by default)
RUN sed -ri 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html

# Keep file permissions readable by Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
