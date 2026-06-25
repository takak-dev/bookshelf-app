# syntax=docker/dockerfile:1

# --- Stage 1: フロントエンドアセットのビルド ---
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# --- Stage 2: PHP アプリケーション ---
FROM php:8.2-cli AS app

# 必要なPHP拡張（pdo_pgsql=Render Postgres / pdo_mysql=互換のため両方）
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 依存（本番のみ）。先に composer ファイルだけコピーしてレイヤキャッシュを効かせる
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# アプリ本体＋ビルド済みアセット
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
