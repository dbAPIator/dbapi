FROM php:7.4-apache-bullseye
RUN apt-get update && apt-get upgrade -y
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql
RUN /usr/sbin/a2enmod rewrite
COPY . /var/www
EXPOSE 80

#CMD ["apache2-foreground"]