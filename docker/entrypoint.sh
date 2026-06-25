#!/usr/bin/env sh
set -e

# APP_KEY は Render の環境変数で設定するのが望ましい。未設定なら起動時に生成（毎デプロイで変わる点に注意）。
if [ -z "$APP_KEY" ]; then
  php artisan key:generate --force
fi

# DBマイグレーション（本番）
php artisan migrate --force

# デモ用初期データ（初回のみ）。reading_plans が空のときだけ seed して重複投入を防ぐ。
NEED_SEED=$(php artisan tinker --execute="echo \App\Models\ReadingPlan::count();" 2>/dev/null | tail -n1)
if [ "$NEED_SEED" = "0" ]; then
  php artisan db:seed --force
fi

# 設定・ルートのキャッシュ
php artisan config:cache
php artisan route:cache

# Render が割り当てる $PORT で待受（デモ用途は artisan serve で十分）
exec php artisan serve --host 0.0.0.0 --port "${PORT:-8080}"
