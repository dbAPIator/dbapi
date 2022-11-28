FROM webdevops/php-nginx

WORKDIR /app
COPY . .
RUN mkdir dbconfigs
VOLUME [ "/app/dbconfigs" ]

EXPOSE 80