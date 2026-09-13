<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('passimark_tags', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('label');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });

        Schema::create('passimark_question_tag', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained('passimark_questions')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('passimark_tags')->cascadeOnDelete();
            $table->primary(['question_id', 'tag_id']);
        });

        Schema::create('passimark_session_tag', function (Blueprint $table) {
            $table->foreignId('session_id')->constrained('passimark_sessions')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('passimark_tags')->cascadeOnDelete();
            $table->primary(['session_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passimark_session_tag');
        Schema::dropIfExists('passimark_question_tag');
        Schema::dropIfExists('passimark_tags');
    }
};