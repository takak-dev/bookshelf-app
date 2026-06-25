<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 読書計画 CRUD・状態絞り込み・読了・認可（PG15-17）のテスト。
 * 認証必須・所有者のみ編集/削除/読了可。
 */
class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/reading-plans')->assertRedirect('/login');
        $this->get('/reading-plans/create')->assertRedirect('/login');
    }

    public function test_index_shows_only_own_plans(): void
    {
        $user = User::factory()->create();
        $mine = ReadingPlan::factory()->create(['user_id' => $user->id]);
        ReadingPlan::factory()->create(); // 他人の計画

        $plans = $this->actingAs($user)->get('/reading-plans')->viewData('readingPlans');

        $this->assertEquals([$mine->id], $plans->pluck('id')->all());
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        $reading = ReadingPlan::factory()->create(['user_id' => $user->id, 'status' => ReadingPlanStatus::Reading]);
        ReadingPlan::factory()->create(['user_id' => $user->id, 'status' => ReadingPlanStatus::Pending]);

        $plans = $this->actingAs($user)
            ->get('/reading-plans?status=reading')
            ->viewData('readingPlans');

        $this->assertEquals([$reading->id], $plans->pluck('id')->all());
    }

    public function test_create_page_lists_books(): void
    {
        Book::factory()->count(2)->create();

        $books = $this->actingAs(User::factory()->create())
            ->get('/reading-plans/create')
            ->viewData('books');

        $this->assertCount(2, $books);
    }

    public function test_store_creates_plan_for_owner_with_pending_status(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post('/reading-plans', [
            'book_id' => $book->id,
            'target_date' => now()->addDays(7)->toDateString(),
        ])->assertRedirect('/reading-plans');

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Pending->value, // 既定は未読
        ]);
    }

    public function test_store_rejects_duplicate_active_plan_for_same_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        // 同一書籍で未完了(読書中)の計画が既存
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Reading,
        ]);

        $this->actingAs($user)->post('/reading-plans', [
            'book_id' => $book->id,
            'target_date' => now()->addDays(7)->toDateString(),
        ])->assertSessionHasErrors('book_id'); // 重複は拒否
    }

    public function test_store_rejects_duplicate_when_expired_plan_exists(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        // 期限切れの計画が既存（再計画は新規でなく「編集で再開」する設計のため新規は不可）
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Expired,
        ]);

        $this->actingAs($user)->post('/reading-plans', [
            'book_id' => $book->id,
            'target_date' => now()->addDays(7)->toDateString(),
        ])->assertSessionHasErrors('book_id');
    }

    public function test_store_allows_new_plan_when_only_completed_plan_exists(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        // 読了済みのみ存在 → 同じ本に新たな計画を作れる（再計画）
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Completed,
        ]);

        $this->actingAs($user)->post('/reading-plans', [
            'book_id' => $book->id,
            'target_date' => now()->addDays(7)->toDateString(),
        ])->assertRedirect('/reading-plans');

        $this->assertSame(2, $user->readingPlans()->where('book_id', $book->id)->count());
    }

    public function test_store_rejects_past_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post('/reading-plans', [
            'book_id' => $book->id,
            'target_date' => now()->subDay()->toDateString(), // 過去日
        ])->assertSessionHasErrors('target_date');
    }

    public function test_owner_can_update_target_date(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Pending,
        ]);
        $newDate = now()->addDays(14)->toDateString();

        $this->actingAs($user)
            ->put("/reading-plans/{$plan->id}", ['target_date' => $newDate])
            ->assertRedirect('/reading-plans');

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id, 'target_date' => $newDate]);
    }

    public function test_updating_expired_plan_reopens_it_as_pending(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Expired,
            'target_date' => now()->subDays(5),
        ]);

        // 期限切れを未来日で更新 → 未読に再開（Q62）
        $this->actingAs($user)
            ->put("/reading-plans/{$plan->id}", ['target_date' => now()->addDays(7)->toDateString()])
            ->assertRedirect('/reading-plans');

        $this->assertSame(ReadingPlanStatus::Pending, $plan->refresh()->status);
    }

    public function test_non_owner_cannot_update(): void
    {
        $plan = ReadingPlan::factory()->create(['status' => ReadingPlanStatus::Pending]);

        $this->actingAs(User::factory()->create()) // 別ユーザー
            ->put("/reading-plans/{$plan->id}", ['target_date' => now()->addDays(3)->toDateString()])
            ->assertForbidden();
    }

    public function test_non_owner_update_is_forbidden_even_with_invalid_input(): void
    {
        $plan = ReadingPlan::factory()->create(['status' => ReadingPlanStatus::Pending]);

        // 不正入力（過去日）でも認可が検証より先＝422でなく403
        $this->actingAs(User::factory()->create())
            ->put("/reading-plans/{$plan->id}", ['target_date' => now()->subDay()->toDateString()])
            ->assertForbidden();
    }

    public function test_completed_plan_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Completed,
        ]);

        // 読了済みは編集不可（Q62）
        $this->actingAs($user)->get("/reading-plans/{$plan->id}/edit")->assertForbidden();
    }

    public function test_completed_plan_cannot_be_completed_again(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Completed,
        ]);

        // 読了済みへの再読了は不可（Q62）
        $this->actingAs($user)->post("/reading-plans/{$plan->id}/complete")->assertForbidden();
    }

    public function test_owner_can_complete_plan(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Reading,
        ]);

        $this->actingAs($user)
            ->post("/reading-plans/{$plan->id}/complete")
            ->assertRedirect('/reading-plans');

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertNotNull($plan->completed_at);
    }

    public function test_non_owner_cannot_complete(): void
    {
        $plan = ReadingPlan::factory()->create(['status' => ReadingPlanStatus::Reading]);

        $this->actingAs(User::factory()->create())
            ->post("/reading-plans/{$plan->id}/complete")
            ->assertForbidden();
    }

    public function test_owner_can_delete_plan(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete("/reading-plans/{$plan->id}")
            ->assertRedirect('/reading-plans');

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_non_owner_cannot_delete(): void
    {
        $plan = ReadingPlan::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete("/reading-plans/{$plan->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }
}
