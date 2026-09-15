<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(){
    // Package format (Sprint 9): stable external UUIDs for update-by-external_id later.
    foreach (['passimark_sessions','passimark_exams','passimark_questions'] as $table) {
      Schema::table($table, function (Blueprint $t) {
        $t->string('external_id', 64)->nullable()->index()->after('id');
      });
    }

    Schema::create('passimark_package_imports', function (Blueprint $t) {
      $t->id();
      $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
      $t->string('original_filename');
      $t->string('package_id', 64);
      $t->char('checksum', 64);
      $t->string('content_type', 32);
      $t->json('summary')->nullable();
      $t->string('status', 24);
      $t->json('error_report')->nullable();
      $t->timestamps();
      $t->index('package_id');
    });
  }

  public function down(){
    Schema::dropIfExists('passimark_package_imports');
    foreach (['passimark_questions','passimark_exams','passimark_sessions'] as $table) {
      Schema::table($table, function (Blueprint $t) { $t->dropColumn('external_id'); });
    }
  }
};