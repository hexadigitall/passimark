<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Sprint 10: Multi-format item taxonomy expansion.
     * Adds type discriminator, interactive configuration payloads, and rich media exhibits
     * to passimark_questions, plus an interaction_response json column on attempt answers.
     */
    public function up(): void
    {
        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->string('type', 32)->default('single_choice')->index()->after('content');
            $table->json('interactive_payload')->nullable()->after('options');
            $table->json('exhibits')->nullable()->after('interactive_payload');
        });

        Schema::table('passimark_attempt_answers', function (Blueprint $table) {
            $table->json('interaction_response')->nullable()->after('selected_option');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_attempt_answers', function (Blueprint $table) {
            $table->dropColumn('interaction_response');
        });

        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->dropColumn(['type', 'interactive_payload', 'exhibits']);
        });
    }
};
