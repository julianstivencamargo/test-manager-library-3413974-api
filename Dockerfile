# Multi-stage Dockerfile para ejecución autónoma sin requerir vendor previo en el host

FROM composer:2.8.2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM php:8.3-cli-alpine
WORKDIR /app

RUN apk add --no-cache curl

# Copiar el código fuente completo
COPY . .

# Copiar las dependencias instaladas de vendor
COPY --from=vendor /app/vendor ./vendor

# Generar el autoload definitivo incluyendo el namespace Library\ mapeado a src/
COPY --from=composer:2.8.2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
