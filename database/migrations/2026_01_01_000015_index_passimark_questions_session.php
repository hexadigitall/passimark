<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->index(['session_id'], 'passimark_questions_session_index');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->dropIndex('passimark_questions_session_index');
        });
    }
};