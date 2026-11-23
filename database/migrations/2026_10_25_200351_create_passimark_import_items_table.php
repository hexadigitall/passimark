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
        if (!Schema::hasTable('passimark_import_items')) {
            Schema::create('passimark_import_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained('passimark_import_batches')->cascadeOnDelete();
                $table->integer('source_index')->default(0);
                $table->string('item_type')->default('single_choice');
                $table->text('prompt');
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
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passimark_import_items');
    }
};
