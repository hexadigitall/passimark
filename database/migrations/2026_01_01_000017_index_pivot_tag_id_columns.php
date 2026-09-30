<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Both pivot tables declare a composite primary key of (owner_id, tag_id), so SQLite only
 * indexes the leading column. Every count-by-tag therefore full-scanned the pivot.
 *
 * PassimarkTag::withCount(['questions', 'sessions']) runs one correlated subquery per tag.
 * With 2,671 tags over a 325,876-row passimark_question_tag that was roughly 870 million row
 * visits and took 94.9s, which pushed /admin/passimark past the 30s execution limit and
 * returned a 500 with an empty body.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passimark_question_tag', function (Blueprint $table) {
            $table->index('tag_id', 'passimark_question_tag_tag_id_index');
        });

        Schema::table('passimark_session_tag', function (Blueprint $table) {
            $table->index('tag_id', 'passimark_session_tag_tag_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_question_tag', function (Blueprint $table) {
            $table->dropIndex('passimark_question_tag_tag_id_index');
        });

        Schema::table('passimark_session_tag', function (Blueprint $table) {
            $table->dropIndex('passimark_session_tag_tag_id_index');
        });
    }
};