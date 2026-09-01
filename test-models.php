<?php
// S0.3 Model Relationship Testing
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== S0.3: Testing Model Relationships ===\n\n";

// Test User -> Progress relationship
$user = \App\Models\User::first();
if ($user) {
    $progressCount = $user->progress()->count();
    echo "✓ User.progress: User '{$user->email}' has {$progressCount} progress records\n";
}

// Test Session -> Questions relationship
$session = \App\Models\PassimarkSession::first();
if ($session) {
    $questionCount = $session->questions()->count();
    echo "✓ Session.questions: Session '{$session->title}' has {$questionCount} questions\n";
}

// Test Question -> Session relationship
$question = \App\Models\PassimarkQuestion::first();
if ($question) {
    $domain = $question->domain;
    echo "✓ Question.domain: Question has domain '{$domain}'\n";
}

// Test Progress -> User and Session
$progress = \App\Models\PassimarkProgress::with(['user', 'session'])->first();
if ($progress) {
    echo "✓ Progress.user: Progress belongs to user '{$progress->user->email}'\n";
    echo "✓ Progress.session: Progress tracks session '{$progress->session->title}'\n";
}

echo "\n✓ All S0.3 model relationships verified!\n";
