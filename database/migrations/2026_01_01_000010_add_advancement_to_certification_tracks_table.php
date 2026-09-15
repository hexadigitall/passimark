<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passimark_certification_tracks', function (Blueprint $table) {
            $table->string('advancement', 16)->default('approval')->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_certification_tracks', function (Blueprint $table) {
            $table->dropColumn('advancement');
        });
    }
};