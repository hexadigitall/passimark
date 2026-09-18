<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Optional (supplemental) sessions — e.g. CISSP mock-error remediation practice — carry
        // questions and are startable, but never gate the required ladder. Required progression
        // skips them and unlocks the next required step instead.
        Schema::table('passimark_sessions', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('is_open')
                ->comment('Supplemental practice: startable when unlocked, but never required for ladder progression');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_sessions', function (Blueprint $table) {
            $table->dropColumn('is_optional');
        });
    }
};
