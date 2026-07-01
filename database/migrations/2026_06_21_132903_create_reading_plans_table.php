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
            // 重複防止はFormRequest側で制御＝DB unique は付けない。
            // 同一(user_id, book_id)は読了後の再計画で複数行あり得るため単純uniqueは不可。
            // 未読/読書中/期限切れが1件でもあれば新規不可、読了(Completed)のみ新規許可。
            // 期限切れは新規でなく既存計画を編集して再開する設計（PM回答）。
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
