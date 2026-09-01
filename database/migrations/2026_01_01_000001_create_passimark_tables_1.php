<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(){
  Schema::create('passimark_sessions', function(Blueprint $t){
    $t->id();
    $t->integer('number');
    $t->integer('phase');
    $t->string('title');
    $t->text('description')->nullable();
    $t->string('domain')->nullable();
    $t->boolean('is_open')->default(false);
    $t->integer('order')->index();
    $t->integer('pass_score')->default(70);
    $t->integer('time_limit')->default(180);
    $t->integer('question_count')->default(25);
    $t->timestamps();
  });
  Schema::create('passimark_exams', function(Blueprint $t){
    $t->id(); $t->foreignId('session_id')->constrained('passimark_sessions')->cascadeOnDelete();
    $t->string('title'); $t->enum('mode',['timed','practice','cat'])->default('cat');
    $t->integer('question_count')->default(150);
    $t->timestamps();
  });
  Schema::create('passimark_questions', function(Blueprint $t){
    $t->id(); $t->foreignId('session_id')->constrained('passimark_sessions')->cascadeOnDelete();
    $t->foreignId('exam_id')->nullable()->constrained('passimark_exams')->nullOnDelete();
    $t->text('content'); $t->json('options');
    $t->float('difficulty')->default(0); $t->float('discrimination')->default(1.0); $t->float('guessing')->default(0.25);
    $t->string('domain'); $t->string('bloom_level')->nullable();
    $t->text('explanation')->nullable(); $t->string('reference')->nullable();
    $t->timestamps();
  });
  Schema::create('passimark_progress', function(Blueprint $t){
    $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->foreignId('session_id')->constrained('passimark_sessions')->cascadeOnDelete();
    $t->enum('status',['locked','open','in_progress','completed','pending_approval','approved'])->default('locked');
    $t->float('score')->nullable(); $t->float('ability_theta')->default(0);
    $t->integer('attempts')->default(0);
    $t->timestamps(); $t->unique(['user_id','session_id']);
  });
  Schema::create('passimark_attempts', function(Blueprint $t){
    $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->foreignId('session_id')->constrained('passimark_sessions')->cascadeOnDelete();
    $t->foreignId('exam_id')->constrained('passimark_exams')->cascadeOnDelete();
    $t->enum('mode',['timed','practice','cat']); $t->float('theta')->default(0);
    $t->timestamp('started_at')->nullable(); $t->timestamp('finished_at')->nullable();
    $t->float('score')->nullable(); $t->boolean('is_passed')->default(false);
    $t->json('responses')->nullable(); $t->timestamps();
  });
  Schema::create('passimark_attempt_answers', function(Blueprint $t){
    $t->id(); $t->foreignId('attempt_id')->constrained('passimark_attempts')->cascadeOnDelete();
    $t->foreignId('question_id')->constrained('passimark_questions')->cascadeOnDelete();
    $t->string('selected_option')->nullable(); $t->boolean('is_correct')->default(false);
    $t->integer('time_spent')->default(0); $t->timestamps();
  });
 }
 public function down(){
  Schema::dropIfExists('passimark_attempt_answers');
  Schema::dropIfExists('passimark_attempts');
  Schema::dropIfExists('passimark_progress');
  Schema::dropIfExists('passimark_questions');
  Schema::dropIfExists('passimark_exams');
  Schema::dropIfExists('passimark_sessions');
 }
};
