<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\CatEngine;
use Database\Seeders\PassimarkSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed(PassimarkSeeder::class);
        $this->student = User::where('email', 'student@passimark.com')->firstOrFail();
        $this->admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $this->fixture = $this->scaffold();
    }

    private function scaffold(): array
    {
        $track = PassimarkCertificationTrack::create(['slug' => 'review-gate', 'title' => 'Review Gate', 'region' => 'TEST', 'advancement' => 'approval']);
        $session = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => 'review', 'number' => 1, 'phase' => 1,
            'phase_type' => 'lesson', 'title' => 'Review Gate Lesson', 'order' => 1, 'is_open' => true,
            'pass_score' => 70, 'questions_target' => 4,
        ]);
        $next = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => 'review', 'number' => 2, 'phase' => 1,
            'phase_type' => 'lesson', 'title' => 'Review Gate Next', 'order' => 2, 'is_open' => false,
        ]);
        $exam = PassimarkExam::create([
            'session_id' => $session->id, 'title' => 'Review Gate Exam', 'mode' => 'practice',
            'question_count' => 4, 'min_questions' => 4, 'max_questions' => 4, 'irt_enabled' => false,
        ]);

        $questions = [];
        $difficulties = [0.5, 0.1, -0.3, -1.0];
        foreach (['Alpha b=0.5', 'Beta b=0.1', 'Gamma b=-0.3', 'Delta b=-1.0'] as $i => $stem) {
            $questions[] = PassimarkQuestion::create([
                'session_id' => $session->id, 'question_number' => $i + 1, 'content' => $stem, 'explanation' => "Explanation::{$stem}",
                'options' => [
                    ['key' => 'a', 'text' => 'Correct option', 'is_correct' => true],
                    ['key' => 'b', 'text' => 'Distractor', 'is_correct' => false],
                ],
                'correct_key' => 'a', 'difficulty' => $difficulties[$i], 'discrimination' => 1.0, 'guessing' => 0.25,
                'domain' => 'TEST', 'bloom_level' => 'remember',
            ]);
        }

        PassimarkProgress::create(['user_id' => $this->student->id, 'session_id' => $session->id, 'status' => 'open', 'attempts' => 0]);

        return compact('track', 'session', 'next', 'exam', 'questions');
    }

    /** @param list<bool> $correct */
    private function runAttempt(array $correct): PassimarkAttempt
    {
        $session = $this->fixture['session'];
        $this->actingAs($this->student)->post("/passimark/session/{$session->id}/start", ['mode' => 'practice'])->assertRedirect();
        $attempt = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $session->id)->where('mode', 'practice')->latest('id')->firstOrFail();

        $correctById = [];
        foreach ($this->fixture['questions'] as $i => $question) {
            $correctById[$question->id] = $correct[$i] ?? false;
        }

        while ($question = CatEngine::nextQuestion($attempt->fresh())) {
            $this->actingAs($this->student)->postJson("/passimark/attempt/{$attempt->id}/answer", [
                'question_id' => $question->id,
                'selected' => $correctById[$question->id] ? 'a' : 'b',
            ])->assertOk();
        }

        return $attempt->fresh();
    }

    public function test_mixed_results_lock_wrong_answers_and_explain_correct_ones(): void
    {
        $attempt = $this->runAttempt([true, true, true, false]);
        $this->assertSame('completed', PassimarkProgress::where('user_id', $this->student->id)->where('session_id', $this->fixture['session']->id)->value('status'));

        $this->actingAs($this->student)->get("/passimark/attempt/{$attempt->id}/result")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Result')
                ->where('review_unlocked', false)
                ->where('progress_status', 'completed')
                ->has('answers', 4)
                ->where('answers.0.exposed', true)
                ->where('answers.0.question.explanation', 'Explanation::Alpha b=0.5')
                ->where('answers.3.exposed', false)
                ->where('answers.3.question.explanation', null)
                ->where('answers.3.question.correct_key', null)
                ->has('answers.3.question.options', 2));
    }

    public function test_all_correct_attempt_unlocks_full_review_without_approval(): void
    {
        $attempt = $this->runAttempt([true, true, true, true]);

        $this->actingAs($this->student)->get("/passimark/attempt/{$attempt->id}/result")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Result')
                ->where('review_unlocked', true)
                ->where('answers.3.exposed', true)
                ->where('answers.3.question.explanation', 'Explanation::Delta b=-1.0')
                ->where('answers.3.question.correct_key', 'a'));
    }

    public function test_instructor_approval_unlocks_the_locked_review(): void
    {
        $attempt = $this->runAttempt([true, true, true, false]);
        $progress = PassimarkProgress::where('user_id', $this->student->id)->where('session_id', $this->fixture['session']->id)->firstOrFail();

        $this->actingAs($this->student)->post("/passimark/session/{$this->fixture['session']->id}/request-approval")->assertRedirect();
        $this->assertSame('pending_approval', $progress->fresh()->status);

        $this->actingAs($this->admin)->postJson("/admin/passimark/progress/{$progress->id}/approve", ['note' => 'Approved to unlock review.'])->assertOk();
        $this->assertSame('approved', $progress->fresh()->status);

        $this->actingAs($this->student)->get("/passimark/attempt/{$attempt->id}/result")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Result')
                ->where('review_unlocked', true)
                ->where('answers.3.exposed', true)
                ->where('answers.3.question.explanation', 'Explanation::Delta b=-1.0')
                ->where('answers.3.question.correct_key', 'a'));
    }

    public function test_review_route_serves_latest_attempt_and_blocks_unstarted_sessions(): void
    {
        $attempt = $this->runAttempt([true, true, true, false]);

        $this->actingAs($this->student)->get("/passimark/session/{$this->fixture['session']->id}/review")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Result')
                ->where('attempt.id', $attempt->id));

        $this->actingAs($this->student)->get("/passimark/session/{$this->fixture['next']->id}/review")->assertStatus(404);
    }

    public function test_exam_payload_hides_answer_keys_outside_practice_mode(): void
    {
        $session = $this->fixture['session'];

        $this->actingAs($this->student)->post("/passimark/session/{$session->id}/start", ['mode' => 'cat'])->assertRedirect();
        $cat = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $session->id)->where('mode', 'cat')->latest('id')->firstOrFail();
        $this->actingAs($this->student)->get(route('passimark.exam', ['attempt' => $cat]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Exam')
                ->missing('question.options.0.is_correct')
                ->missing('question.correct_key'));

        $question = CatEngine::nextQuestion($cat);
        $this->actingAs($this->student)->postJson("/passimark/attempt/{$cat->id}/answer", ['question_id' => $question->id, 'selected' => 'a'])
            ->assertOk()
            ->assertJsonMissingPath('next.options.0.is_correct')
            ->assertJsonMissingPath('next.correct_key');

        $this->actingAs($this->student)->post("/passimark/session/{$session->id}/start", ['mode' => 'practice'])->assertRedirect();
        $practice = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $session->id)->where('mode', 'practice')->latest('id')->firstOrFail();
        $this->actingAs($this->student)->get(route('passimark.exam', ['attempt' => $practice]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Exam')
                ->where('question.options.0.is_correct', true));
    }
}