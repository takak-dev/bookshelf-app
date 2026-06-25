<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 本番デプロイ時の最適化コマンドが通ることを保証するスモークテスト。
 * route:cache は重複ルート名があると失敗するため、回帰防止に有効。
 */
class ProductionCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        // テストで作成したキャッシュを残さない
        $this->artisan('route:clear');
        $this->artisan('config:clear');

        parent::tearDown();
    }

    public function test_routes_can_be_cached(): void
    {
        // 重複ルート名等があるとここで失敗する（本番の route:cache 相当）
        $this->artisan('route:cache')->assertSuccessful();
    }

    public function test_config_can_be_cached(): void
    {
        $this->artisan('config:cache')->assertSuccessful();
    }
}
