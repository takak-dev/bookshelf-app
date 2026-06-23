<?php

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reading_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->date('target_date');
            $table->string('status')->default(ReadingPlanStatus::Pending->value);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            // 重複防止はFormRequest側（未完了1件まで）で制御＝DB unique は付けない（完了/失効後の再計画を許すため）
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_plans');
    }
};
