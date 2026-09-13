<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Worldwide catalog: track regions for dashboard grouping + per-cert metadata.
        Schema::table('passimark_certification_tracks', function (Blueprint $table) {
            $table->string('region')->nullable()->after('title');
        });

        // Sessions graduate from a single-curriculum phase model to the v4 5-stage CAM ladder.
        Schema::table('passimark_sessions', function (Blueprint $table) {
            $table->string('phase_type')->nullable()->after('phase')
                ->comment('v4 ladder stage: cert|lesson|phase|domain|mock|final (null = legacy prototype)');
            $table->string('cert_slug')->nullable()->after('certification_track_id');
            $table->float('theta_required')->nullable()->after('pass_score');
            $table->integer('questions_target')->nullable()->after('question_count');
            $table->integer('time_minutes')->nullable()->after('time_limit');
        });

        // Auto-alias the legacy columns for pre-existing (CISSP/demo) sessions so reads never break.
        DB::table('passimark_sessions')->update([
            'questions_target' => DB::raw('question_count'),
            'time_minutes' => DB::raw('time_limit'),
        ]);

        // Exam-level v4 fields: per-mode time, final flag, IRT switch (CAT only).
        Schema::table('passimark_exams', function (Blueprint $table) {
            $table->integer('time_minutes')->nullable()->after('mode');
            $table->boolean('is_final')->default(false)->after('time_minutes');
            $table->boolean('irt_enabled')->default(false)->after('is_final');
        });

        foreach (DB::table('passimark_sessions')->get(['id', 'time_limit']) as $session) {
            DB::table('passimark_exams')->where('session_id', $session->id)->update(['time_minutes' => $session->time_limit]);
        }

        // Questions: explicit correct-answer key + IRT chapter allows a clean Sprint 6 migration.
        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->string('correct_key')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_questions', function (Blueprint $table) {
            $table->dropColumn('correct_key');
        });
        Schema::table('passimark_exams', function (Blueprint $table) {
            $table->dropColumn(['time_minutes', 'is_final', 'irt_enabled']);
        });
        Schema::table('passimark_sessions', function (Blueprint $table) {
            $table->dropColumn(['phase_type', 'cert_slug', 'theta_required', 'questions_target', 'time_minutes']);
        });
        Schema::table('passimark_certification_tracks', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};