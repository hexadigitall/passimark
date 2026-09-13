<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('passimark_certification_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('passimark_sessions', function (Blueprint $table) {
            $table->foreignId('certification_track_id')->nullable()->after('id')->constrained('passimark_certification_tracks')->nullOnDelete();
        });

        // Backfill any pre-existing sessions onto a default track so no session is ever left untracked.
        $defaultTrackId = DB::table('passimark_certification_tracks')->insertGetId([
            'slug' => 'cissp',
            'title' => 'CISSP',
            'description' => 'Certified Information Systems Security Professional preparation track.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('passimark_sessions')->whereNull('certification_track_id')->update(['certification_track_id' => $defaultTrackId]);
    }

    public function down(): void
    {
        Schema::table('passimark_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certification_track_id');
        });
        Schema::dropIfExists('passimark_certification_tracks');
    }
};
