<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('passimark_import_batches') && !Schema::hasColumn('passimark_import_batches', 'source_type')) {
            Schema::table('passimark_import_batches', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source_type')->default('csv');
                $table->string('original_filename')->default('import');
                $table->string('file_path')->nullable();
                $table->string('checksum', 64)->default('')->index();
                $table->string('status')->default('staged');
                $table->integer('total_items')->default(0);
                $table->integer('valid_items')->default(0);
                $table->integer('error_items')->default(0);
                $table->json('metadata')->nullable();
                $table->json('error_log')->nullable();
            });
        }

        if (Schema::hasTable('passimark_import_items') && !Schema::hasColumn('passimark_import_items', 'prompt')) {
            Schema::table('passimark_import_items', function (Blueprint $table) {
                $table->foreignId('batch_id')->nullable()->constrained('passimark_import_batches')->cascadeOnDelete();
                $table->integer('source_index')->default(0);
                $table->string('item_type')->default('single_choice');
                $table->text('prompt')->nullable();
                $table->json('choices')->nullable();
                $table->json('correct_answers')->nullable();
                $table->text('explanation')->nullable();
                $table->string('reference')->nullable();
                $table->string('domain')->nullable();
                $table->string('bloom')->nullable();
                $table->float('difficulty')->nullable()->default(0.0);
                $table->float('discrimination')->nullable()->default(1.0);
                $table->float('guessing')->nullable()->default(0.25);
                $table->json('interactive_payload')->nullable();
                $table->json('exhibits')->nullable();
                $table->string('status')->default('valid');
                $table->json('validation_errors')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op or drop columns
    }
};
