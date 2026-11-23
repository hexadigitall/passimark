<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passimark_attempts', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('finished_at');
            $table->integer('remaining_seconds')->nullable()->after('paused_at');
            $table->json('session_state')->nullable()->after('remaining_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_attempts', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'remaining_seconds', 'session_state']);
        });
    }
};
