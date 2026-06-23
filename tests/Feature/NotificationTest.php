<?php

namespace Tests\Feature;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 通知一覧・既読化（PG18）のテスト。
 * 通知は DatabaseChannel に保存され、本人のみ既読化できる。
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_index_lists_own_notifications(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);
        // DatabaseChannel へ通知を保存
        $user->notify(new ReadingPlanReminder($plan, ReminderTiming::OnDueDate));

        $notifications = $this->actingAs($user)->get('/notifications')->viewData('notifications');

        $this->assertCount(1, $notifications);
        $this->assertSame('on_due_date', $notifications->first()->data['timing']);
    }

    public function test_owner_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $user->id]);
        $user->notify(new ReadingPlanReminder($plan, ReminderTiming::OnDueDate));
        $id = $user->notifications()->first()->id;

        $this->actingAs($user)
            ->post("/notifications/{$id}/read")
            ->assertRedirect('/notifications');

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_cannot_mark_others_notification(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->create(['user_id' => $owner->id]);
        $owner->notify(new ReadingPlanReminder($plan, ReminderTiming::OnDueDate));
        $id = $owner->notifications()->first()->id;

        // 別ユーザーは他人の通知を既読化できない（404）
        $this->actingAs(User::factory()->create())
            ->post("/notifications/{$id}/read")
            ->assertNotFound();

        $this->assertNull($owner->notifications()->first()->read_at);
    }
}
