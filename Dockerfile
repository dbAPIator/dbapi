FROM php:7.4-apache-bullseye
COPY . /var/www/html
RUN a2enmod headers rewrite
RUN apt-get update
RUN ["apt","search","php7.4"]
RUN docker-php-ext-install mysqli

EXPOSE 80

CMD ["apache2-foreground"]