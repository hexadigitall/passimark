<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificate issuance (Sprint 8): a credential is minted when a learner passes a track's
     * final assessment. `credential_hash` is a tamper-evident placeholder reserved for future
     * Polygon/IPFS anchoring.
     */
    public function up(): void
    {
        Schema::table('passimark_progress', function (Blueprint $table) {
            $table->timestamp('certified_at')->nullable()->after('status');
            $table->string('credential_id')->nullable()->unique()->after('certified_at');
            $table->float('pass_probability')->nullable()->after('score');
            $table->string('credential_hash')->nullable()->after('credential_id');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_progress', function (Blueprint $table) {
            $table->dropUnique(['credential_id']);
            $table->dropColumn(['certified_at', 'credential_id', 'pass_probability', 'credential_hash']);
        });
    }
};
