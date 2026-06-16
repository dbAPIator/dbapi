FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y software-properties-common curl unzip \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y \
        nginx \
        supervisor \
        php7.4-cli \
        php7.4-fpm \
        php7.4-mysql \
        php7.4-redis \
        php7.4-json \
        php7.4-opcache \
        php7.4-apcu \
        php7.4-mbstring \
        php7.4-xml \
        php7.4-zip \
        php7.4-yaml \
        php7.4-curl \
    && php -r "if (!extension_loaded('yaml')) { fwrite(STDERR, 'ext-yaml is required but not loaded\n'); exit(1); }" \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY configs/nginx.conf /etc/nginx/sites-available/default
COPY configs/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY configs/php.ini /etc/php/7.4/fpm/php.ini
COPY configs/php-fpm.conf /etc/php/7.4/fpm/php-fpm.conf
COPY configs/www.conf /etc/php/7.4/fpm/pool.d/www.conf

RUN sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g' /etc/php/7.4/fpm/php.ini \
    && mkdir -p /run/php /app/apis

WORKDIR /app

COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY src/ ./

RUN chown -R www-data:www-data /app/public

COPY scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=15s --retries=3 \
    CMD curl -f http://127.0.0.1/ || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
