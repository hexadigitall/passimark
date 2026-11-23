<?php

namespace Tests\Feature;

use App\Models\PassimarkAttempt;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualModeNavigationAndProctoringTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private PassimarkSession $session;
    private PassimarkExam $timedExam;
    private PassimarkExam $catExam;
    private PassimarkQuestion $q1;
    private PassimarkQuestion $q2;
    private PassimarkQuestion $q3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::create([
            'name' => 'Proctored Candidate',
            'email' => 'proctored@passimark.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $track = PassimarkCertificationTrack::create([
            'title' => 'Navigation Track',
            'slug' => 'nav-track',
            'cert_key' => 'nav',
            'is_active' => true,
        ]);

        $this->session = PassimarkSession::create([
            'certification_track_id' => $track->id,
            'number' => 1,
            'phase' => 1,
            'title' => 'Navigation & Proctoring Lab',
            'order' => 1,
            'pass_score' => 70,
            'question_count' => 3,
        ]);

        $this->timedExam = PassimarkExam::create([
            'session_id' => $this->session->id,
            'title' => 'Timed Exam',
            'mode' => 'timed',
            'time_minutes' => 30,
            'question_count' => 3,
            'irt_enabled' => false,
        ]);

        $this->catExam = PassimarkExam::create([
            'session_id' => $this->session->id,
            'title' => 'CAT Exam',
            'mode' => 'cat',
            'question_count' => 3,
            'irt_enabled' => true,
        ]);

        PassimarkProgress::create([
            'user_id' => $this->candidate->id,
            'session_id' => $this->session->id,
            'status' => 'open',
            'ability_theta' => 0.0,
        ]);

        $this->q1 = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->timedExam->id,
            'type' => 'single_choice',
            'content' => 'First Question',
            'options' => [
                ['key' => 'A', 'text' => 'Ans A', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Ans B', 'is_correct' => false],
            ],
            'correct_key' => 'A',
            'difficulty' => -1.0,
        ]);

        $this->q2 = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->timedExam->id,
            'type' => 'single_choice',
            'content' => 'Second Question',
            'options' => [
                ['key' => 'A', 'text' => 'Ans A', 'is_correct' => false],
                ['key' => 'B', 'text' => 'Ans B', 'is_correct' => true],
            ],
            'correct_key' => 'B',
            'difficulty' => 0.0,
        ]);

        $this->q3 = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->timedExam->id,
            'type' => 'single_choice',
            'content' => 'Third Question',
            'options' => [
                ['key' => 'A', 'text' => 'Ans A', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Ans B', 'is_correct' => false],
            ],
            'correct_key' => 'A',
            'difficulty' => 1.0,
        ]);
    }

    public function test_non_linear_free_roam_navigation_in_timed_mode(): void
    {
        $attempt = PassimarkAttempt::create([
            'user_id' => $this->candidate->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->timedExam->id,
            'mode' => 'timed',
            'started_at' => now(),
        ]);

        // 1. Candidate views exam page and receives full preloaded questions array
        $res = $this->actingAs($this->candidate)->get("/passimark/attempt/{$attempt->id}");
        $res->assertOk();
        $pageProps = $res->viewData('page')['props'];
        $this->assertCount(3, $pageProps['questions']);
        $this->assertEquals(0, $pageProps['answeredCount']);

        // 2. Candidate jumps to question 2 FIRST (skipping question 1) and answers it
        $ans2 = $this->actingAs($this->candidate)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $this->q2->id,
            'selected' => 'B',
            'time_spent' => 15,
        ]);
        $ans2->assertOk();
        $ans2->assertJson(['correct' => true, 'score' => 1.0, 'answeredCount' => 1]);

        // Verify only 1 answer row exists in database
        $this->assertDatabaseCount('passimark_attempt_answers', 1);

        // 3. Candidate jumps back to question 1 and answers it
        $ans1 = $this->actingAs($this->candidate)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $this->q1->id,
            'selected' => 'A',
            'time_spent' => 10,
        ]);
        $ans1->assertOk();
        $ans1->assertJson(['correct' => true, 'score' => 1.0, 'answeredCount' => 2]);
        $this->assertDatabaseCount('passimark_attempt_answers', 2);

        // 4. Candidate re-visits question 2 and changes their answer to 'A' (incorrect)
        $ans2Update = $this->actingAs($this->candidate)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $this->q2->id,
            'selected' => 'A',
            'time_spent' => 5,
        ]);
        $ans2Update->assertOk();
        $ans2Update->assertJson(['correct' => false, 'score' => 0.0, 'answeredCount' => 2]);

        // Database still only has 2 records, with Q2 updated
        $this->assertDatabaseCount('passimark_attempt_answers', 2);
        $this->assertDatabaseHas('passimark_attempt_answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_option' => 'A',
            'is_correct' => false,
        ]);
    }

    public function test_strict_linear_enforcement_in_cat_mode(): void
    {
        $attempt = PassimarkAttempt::create([
            'user_id' => $this->candidate->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->catExam->id,
            'mode' => 'cat',
            'started_at' => now(),
            'theta' => 0.0,
        ]);

        // Attempt should only provide single question, not array of questions
        $res = $this->actingAs($this->candidate)->get("/passimark/attempt/{$attempt->id}");
        $res->assertOk();
        $pageProps = $res->viewData('page')['props'];
        $this->assertNull($pageProps['questions']);
        $this->assertNotNull($pageProps['question']);

        $nextCatQuestionId = $pageProps['question']['id'];

        // Find a question that is NOT the next adaptive question
        $otherQuestion = PassimarkQuestion::where('session_id', $this->session->id)
            ->where('id', '!=', $nextCatQuestionId)
            ->first();

        // Submitting an out-of-order question in CAT mode must be rejected with 422
        $ansOutOrder = $this->actingAs($this->candidate)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $otherQuestion->id,
            'selected' => 'A',
        ]);
        $ansOutOrder->assertStatus(422);
    }

    public function test_stateful_pause_resume_and_snapshot_sync(): void
    {
        $attempt = PassimarkAttempt::create([
            'user_id' => $this->candidate->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->timedExam->id,
            'mode' => 'timed',
            'started_at' => now(),
        ]);

        // 1. Pause attempt
        $pauseRes = $this->actingAs($this->candidate)->postJson("/passimark/attempt/{$attempt->id}/pause", [
            'remaining_seconds' => 1250,
            'session_state' => [
                'current_index' => 1,
                'flagged' => [2],
                'notes' => 'Checking CIDR 192.168.1.0/24',
            ],
        ]);
        $pauseRes->assertOk();
        $pauseRes->assertJson(['status' => 'paused', 'remaining_seconds' => 1250]);

        $attempt->refresh();
        $this->assertNotNull($attempt->paused_at);
        $this->assertEquals(1250, $attempt->remaining_seconds);
        $this->assertEquals('Checking CIDR 192.168.1.0/24', $attempt->session_state['notes']);

        // 2. Exam page shows paused state
        $examRes = $this->actingAs($this->candidate)->get("/passimark/attempt/{$attempt->id}");
        $examRes->assertOk();
        $this->assertTrue($examRes->viewData('page')['props']['isPaused']);
        $this->assertEquals(1250, $examRes->viewData('page')['props']['remainingSeconds']);

        // 3. Resume attempt
        $resumeRes = $this->actingAs($this->candidate)->postJson("/passimark/attempt/{$attempt->id}/resume");
        $resumeRes->assertOk();
        $resumeRes->assertJson(['status' => 'resumed', 'remaining_seconds' => 1250]);

        $attempt->refresh();
        $this->assertNull($attempt->paused_at);

        // 4. Periodic state snapshot save
        $stateRes = $this->actingAs($this->candidate)->postJson("/passimark/attempt/{$attempt->id}/state", [
            'remaining_seconds' => 1200,
            'session_state' => [
                'current_index' => 2,
                'flagged' => [2, 3],
            ],
        ]);
        $stateRes->assertOk();

        $attempt->refresh();
        $this->assertEquals(1200, $attempt->remaining_seconds);
        $this->assertEquals([2, 3], $attempt->session_state['flagged']);
    }
}
