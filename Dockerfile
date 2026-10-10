FROM php:8.3-cli-alpine

WORKDIR /app

RUN apk add --no-cache curl

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
