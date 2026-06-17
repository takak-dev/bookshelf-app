# BookShelf（本棚）

書籍を登録・閲覧し、レビュー投稿・お気に入り・いいね・ジャンル管理・評価ランキングができる書籍レビューアプリケーションです。外部アプリ向けの公開API（JSON）も提供します。

## 概要

- 書籍のCRUD（一覧／詳細／登録／編集／削除）。編集・削除は作成者のみ。
- ジャンル管理（多対多）。書籍が紐づくジャンルは削除不可。
- レビュー投稿・編集・削除（1ユーザー1書籍1件・自己レビュー禁止・投稿者のみ編集削除）。
- お気に入り（トグル）・レビューへのいいね（トグル・自己いいね禁止）。
- レビュー平均評価ランキング TOP10。
- 認証は Laravel Fortify（会員登録／ログイン／ログアウト）。
- 公開API（認証なしの書籍CRUD・API Resource整形）。

## 使用技術

| 分類 | 技術 |
|---|---|
| 言語 | PHP 8.x |
| フレームワーク | Laravel 10.x |
| 認証 | Laravel Fortify（応用で Sanctum） |
| DB | MySQL 8.4 |
| 開発環境 | Laravel Sail（Docker）/ phpMyAdmin |
| フロント | Blade / Tailwind CSS / Vite / Alpine.js |
| 品質 | Laravel Pint（整形）/ PHPUnit（テスト・カバレッジ約83%） |

## ER図

```mermaid
erDiagram
    users ||--o{ books : "登録する(user_id)"
    users ||--o{ reviews : "投稿する(user_id)"
    users ||--o{ favorites : "お気に入り(user_id)"
    users ||--o{ review_likes : "いいね(user_id)"
    books ||--o{ reviews : "対象(book_id)"
    books ||--o{ favorites : "対象(book_id)"
    books ||--o{ book_genre : ""
    genres ||--o{ book_genre : ""
    reviews ||--o{ review_likes : "対象(review_id)"

    users {
        bigint id PK "サロゲートキー"
        varchar name "ユーザー名"
        varchar email UK "メールアドレス"
        timestamp email_verified_at "メール確認日時(nullable)"
        varchar password "パスワード(ハッシュ化)"
        varchar remember_token "ログイン保持トークン(nullable)"
        text two_factor_secret "2要素認証秘密鍵(nullable/Fortify)"
        text two_factor_recovery_codes "2要素認証リカバリコード(nullable/Fortify)"
        timestamp two_factor_confirmed_at "2要素認証確認日時(nullable/Fortify)"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }
    books {
        bigint id PK "サロゲートキー"
        varchar title "タイトル"
        varchar author "著者"
        varchar isbn UK "ISBN-13(13桁・一意)"
        date published_date "出版日"
        text description "説明(nullable)"
        varchar image_url "画像URL(nullable)"
        bigint user_id FK "登録ユーザーID"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }
    genres {
        bigint id PK "サロゲートキー"
        varchar name UK "ジャンル名"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }
    reviews {
        bigint id PK "サロゲートキー"
        bigint user_id FK "投稿者ID"
        bigint book_id FK "対象書籍ID"
        tinyint rating "評価値(1-5)"
        text comment "コメント内容"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
        unique user_book_unique "UNIQUE(user_id, book_id)"
    }
    book_genre {
        bigint book_id PK "書籍ID(複合キー)"
        bigint genre_id PK "ジャンルID(複合キー)"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }
    favorites {
        bigint user_id PK "ユーザーID(複合キー)"
        bigint book_id PK "書籍ID(複合キー)"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }
    review_likes {
        bigint user_id PK "ユーザーID(複合キー)"
        bigint review_id PK "レビューID(複合キー)"
        timestamp created_at "作成日時"
        timestamp updated_at "更新日時"
    }
```

※ reviews は (user_id, book_id) で一意。book_genre / favorites / review_likes は複合主キー。外部キーは ON DELETE CASCADE。
※ users の `two_factor_*` 3カラムは Fortify 標準（基本機能では2要素認証フローは未実装）。
※ Laravel/Sanctum 標準テーブル（`password_reset_tokens` / `failed_jobs` / `personal_access_tokens`）はドメイン関連を持たないため ER 図からは省略（DBには存在）。応用機能（読書計画・通知）のテーブルは Phase2 で追加。

## 環境構築

```bash
git clone <repository-url>
cd bookshelf-app

cp .env.example .env

# 依存インストール（初回・vendor が無い場合）
docker run --rm -v "$(pwd):/var/www/html" -w /var/www/html \
  laravelsail/php82-composer:latest composer install

# 起動
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# フロント
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev   # 開発サーバ（常駐するため別ターミナルで起動したままにする）
```

> `.env` の DB 接続はコンテナ名を使用：`DB_HOST=mysql` / `DB_DATABASE=laravel` / `DB_USERNAME=sail` / `DB_PASSWORD=password`

初期ユーザー（シーダー投入・パスワードは全員 `password`）：
`yamada@example.com` / `suzuki@example.com` / `tanaka@example.com` / `sato@example.com` / `takahashi@example.com`

## 開発環境URL

| 用途 | URL |
|---|---|
| アプリ | http://localhost |
| phpMyAdmin | http://localhost:8080 |

## 公開API エンドポイント

ベースURL: `/api/v1`（認証なし・JSON）

| メソッド | URI | 説明 |
|---|---|---|
| GET | `/api/v1/books` | 書籍一覧（`keyword`／`genre`／`per_page` 対応・20件/ページ） |
| GET | `/api/v1/books/{book}` | 書籍詳細（ジャンル・レビュー含む） |
| POST | `/api/v1/books` | 書籍登録 |
| PUT | `/api/v1/books/{book}` | 書籍更新 |
| DELETE | `/api/v1/books/{book}` | 書籍削除 |

## テスト

```bash
./vendor/bin/sail artisan test
```

## 作成者

taka-dev
