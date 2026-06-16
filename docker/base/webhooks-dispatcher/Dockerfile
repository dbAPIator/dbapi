FROM php:8.3-cli-alpine

RUN set -ex \
    && apk add --no-cache curl-dev \
    && apk add --no-cache --virtual .build-deps ${PHPIZE_DEPS} \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install curl \
    && apk del .build-deps

WORKDIR /app
COPY webhooks-dispatcher.php ./

CMD ["php", "webhooks-dispatcher.php"]
