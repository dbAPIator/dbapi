FROM ubuntu:22.04

# Avoid prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive

# Install software-properties-common to get add-apt-repository command
RUN apt-get update && apt-get install -y software-properties-common

# Add PHP 7.4 repository
RUN add-apt-repository ppa:ondrej/php -y

# Update and install required packages
RUN apt-get update && apt-get install -y \
    nginx \
    php7.4-fpm \
    php7.4-mysql \
    php7.4-pgsql \
    php7.4-redis \
    php7.4-json \
    php7.4-opcache \
    php7.4-apcu \
    php7.4-mbstring \
    php7.4-xml \
    php7.4-zip \
    php7.4-yaml \
    php7.4-sqlite3 \
    php7.4-xml \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure nginx
COPY configs/nginx.conf /etc/nginx/sites-available/default

# Configure supervisord
COPY configs/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# configure php
COPY configs/php.ini /etc/php/7.4/fpm/php.ini
COPY configs/php-fpm.conf /etc/php/7.4/fpm/php-fpm.conf
COPY configs/www.conf /etc/php/7.4/fpm/pool.d/www.conf

# Configure PHP-FPM
RUN sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g' /etc/php/7.4/fpm/php.ini && \
    sed -i 's/listen = \/run\/php\/php7.4-fpm.sock/listen = 9000/g' /etc/php/7.4/fpm/pool.d/www.conf


# Create directory for PHP-FPM socket
RUN mkdir -p /run/php

# Create web directory
RUN mkdir -p /app
WORKDIR /app

# Copy application files
COPY ./src/ /app/

# Set proper permissions
RUN chown -R www-data:www-data /app/public

# Expose port 80
EXPOSE 80

# Start supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]