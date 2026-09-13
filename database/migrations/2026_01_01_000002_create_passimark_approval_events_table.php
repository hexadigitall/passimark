<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('passimark_approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_id')->constrained('passimark_progress')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['progress_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passimark_approval_events');
    }
};
