<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('passimark_spaced_repetitions')) {
            Schema::create('passimark_spaced_repetitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained('passimark_questions')->cascadeOnDelete();
                $table->unsignedTinyInteger('box')->default(1);
                $table->unsignedInteger('consecutive_correct')->default(0);
                $table->unsignedInteger('review_count')->default(0);
                $table->timestamp('last_reviewed_at')->nullable();
                $table->timestamp('next_review_at')->index();
                $table->float('difficulty_modifier')->default(1.0);
                $table->timestamps();

                $table->unique(['user_id', 'question_id']);
                $table->index(['user_id', 'next_review_at']);
                $table->index(['user_id', 'box']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('passimark_spaced_repetitions');
    }
};
