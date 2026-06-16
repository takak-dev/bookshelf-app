<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $review = Review::factory()->create();

        $this->post(route('reviews.like', $review))->assertRedirect('/login');
    }

    public function test_toggle_adds_then_removes_like(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(); // 別ユーザーのレビュー

        // いいね追加
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertDatabaseHas('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);

        // いいね解除
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertDatabaseMissing('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
    }

    public function test_cannot_like_own_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('reviews.like', $review))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
    }
}
