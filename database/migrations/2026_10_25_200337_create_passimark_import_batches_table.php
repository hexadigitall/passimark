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
        if (!Schema::hasTable('passimark_import_batches')) {
            Schema::create('passimark_import_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source_type');
                $table->string('original_filename');
                $table->string('file_path')->nullable();
                $table->string('checksum', 64)->index();
                $table->string('status')->default('staged');
                $table->integer('total_items')->default(0);
                $table->integer('valid_items')->default(0);
                $table->integer('error_items')->default(0);
                $table->json('metadata')->nullable();
                $table->json('error_log')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passimark_import_batches');
    }
};
