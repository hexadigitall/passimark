<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passimark_exams', function (Blueprint $table) {
            $table->unsignedInteger('min_questions')->nullable()->after('question_count');
            $table->unsignedInteger('max_questions')->nullable()->after('min_questions');
        });
    }

    public function down(): void
    {
        Schema::table('passimark_exams', function (Blueprint $table) {
            $table->dropColumn(['min_questions', 'max_questions']);
        });
    }
};