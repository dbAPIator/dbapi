FROM php:7.4-apache-bullseye
RUN apt-get update && apt-get upgrade -y
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql
RUN /usr/sbin/a2enmod rewrite
RUN /usr/sbin/a2enmod headers
RUN apt-get install -y vim less
COPY . /var/www
RUN cp "/var/www/php.ini" "$PHP_INI_DIR/php.ini"

EXPOSE 80

#CMD ["apache2-foreground"]