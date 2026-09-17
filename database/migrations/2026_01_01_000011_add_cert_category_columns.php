<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Multi-bundle catalog (Sprint 7.6): a certification is a *category* (cert_key,
        // e.g. `cissp`); each certification_tracks row becomes one *bundle/variant* under
        // that category. Multiple rows can share a cert_key; slug stays globally unique.
        Schema::table('passimark_certification_tracks', function (Blueprint $table) {
            $table->string('cert_key')->nullable()->after('slug')->index();
            $table->string('variant_label')->nullable()->after('title');
            $table->string('source')->nullable()->after('variant_label')
                ->comment('Bundle provenance: seed:<class> or import:<package_id>');
        });

        // Backfill: every legacy track becomes its own certification category so no
        // behavior changes for single-bundle installs. The legacy marker lets the first
        // real bundle (or the worldwide catalog) adopt the placeholder row instead of
        // forking it into a variant.
        DB::table('passimark_certification_tracks')
            ->whereNull('cert_key')
            ->update(['cert_key' => DB::raw('slug'), 'source' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('passimark_certification_tracks', function (Blueprint $table) {
            $table->dropIndex(['cert_key']);
            $table->dropColumn(['cert_key', 'variant_label', 'source']);
        });
    }
};