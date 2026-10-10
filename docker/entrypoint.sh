#!/bin/sh
# Entrypoint del backend (spec-laravel/t07-t17-backend-api-core.md §5.3).
# Permite arrancar desde un clon limpio sin crear un .env a mano.
# No ejecuta migraciones: la base arranca sin tablas hasta correr `php artisan migrate` (T-20).
set -e

cd /app

# 1. Derivar el .env local desde la plantilla versionada (el .env queda ignorado por Git).
if [ ! -f .env ]; then
    cp .env.example .env
    echo "[entrypoint] .env creado a partir de .env.example"
fi

# 2. Generar una APP_KEY local si no viene del entorno ni del .env. Nunca se versiona.
if [ -z "${APP_KEY}" ] && ! grep -Eq '^APP_KEY=.+' .env; then
    php artisan key:generate --force --no-interaction
fi

# 3. Evitar configuración cacheada obsoleta.
php artisan config:clear > /dev/null 2>&1 || true

exec "$@"
