# Multi-stage Dockerfile para ejecución autónoma sin requerir dependencias en el host

FROM composer:2.8.2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM php:8.3-cli-alpine
WORKDIR /app

# Instalar curl y librerías de postgresql para pdo_pgsql
RUN apk add --no-cache curl postgresql-dev \
    && docker-php-ext-install pdo_pgsql

# Copiar el código fuente completo
COPY . .

# Copiar las dependencias instaladas de vendor (incluyendo dependencias de test)
COPY --from=vendor /app/vendor ./vendor

# Generar el autoload definitivo incluyendo el namespace Library\ mapeado a src/
COPY --from=composer:2.8.2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize

# Entrypoint: deriva .env desde .env.example y genera APP_KEY local en clones limpios.
# Se instala fuera de /app (montado como volumen) y se normalizan finales de línea CRLF de Windows.
COPY docker/entrypoint.sh /usr/local/bin/ml-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/ml-entrypoint && chmod +x /usr/local/bin/ml-entrypoint

EXPOSE 8000

ENTRYPOINT ["ml-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
