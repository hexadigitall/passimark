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
use Tests\TestCase;

class CatEngineV4Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed(PassimarkSeeder::class);
        $this->student = User::where('email', 'student@passimark.com')->firstOrFail();
    }

    public function test_v4_cat_prefers_near_theta_items_then_maximises_fisher_information_and_excludes_answered(): void
    {
        $fixture = $this->track(1);
        $qNear = $this->question($fixture['session']->id, 0.5, 0.5);
        $qFarLower = $this->question($fixture['session']->id, -2.0, 1.5);
        $qFarHigher = $this->question($fixture['session']->id, -2.0, 2.0);

        $attempt = PassimarkAttempt::create([
            'user_id' => $this->student->id, 'session_id' => $fixture['session']->id,
            'exam_id' => $fixture['exam']->id, 'mode' => 'cat', 'theta' => 0.5, 'started_at' => now(),
        ]);

        $this->assertSame($qNear->id, CatEngine::nextQuestion($attempt)?->id);

        $attempt->answers()->create(['question_id' => $qNear->id, 'selected_option' => 'a', 'is_correct' => true]);
        $attempt = $attempt->fresh();
        $this->assertNotSame($qNear->id, CatEngine::nextQuestion($attempt)?->id);
        $this->assertSame($qFarLower->id, CatEngine::nextQuestion($attempt)?->id);
    }

    public function test_v4_cat_runs_the_full_fixed_length_pool_and_passes_by_theta(): void
    {
        $thetaRequired = 0.6;
        $fixture = $this->track(5, $thetaRequired);
        $questions = $this->pool($fixture['session']->id, [2.0, 1.0, 0.0, -1.0, -2.0], 2.0);

        $response = $this->actingAs($this->student)->post("/passimark/session/{$fixture['session']->id}/start", ['mode' => 'cat']);
        $attempt = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $fixture['session']->id)->firstOrFail();
        $response->assertRedirect(route('passimark.exam', ['attempt' => $attempt]));

        $answered = 0;
        while ($question = CatEngine::nextQuestion($attempt->fresh())) {
            $this->actingAs($this->student)->postJson("/passimark/attempt/{$attempt->id}/answer", [
                'question_id' => $question->id, 'selected' => 'a',
            ])->assertOk();
            $answered++;
        }

        $this->assertSame(5, $answered);
        $this->assertSame(5, $attempt->fresh()->answers()->count());
        $attempt->refresh();
        $this->assertNotNull($attempt->finished_at);
        $this->assertTrue((bool) $attempt->is_passed);
        $this->assertGreaterThanOrEqual($thetaRequired, $attempt->theta);
        $this->assertGreaterThan(50.0, $attempt->score);

        $this->assertSame('completed', PassimarkProgress::where('user_id', $this->student->id)->where('session_id', $fixture['session']->id)->value('status'));
        $this->assertDatabaseHas('passimark_progress', [
            'user_id' => $this->student->id, 'session_id' => $fixture['next']->id, 'status' => 'open',
        ]);

        $result = $this->actingAs($this->student)->postJson("/passimark/attempt/{$attempt->id}/finish");
        $result->assertOk()->assertJson(['theta' => $attempt->theta]);
        $this->assertGreaterThan(0, (int) $fixture['exam']->fresh()->min_questions);
        $this->assertSame((int) $fixture['exam']->fresh()->min_questions, (int) $fixture['exam']->fresh()->max_questions);
    }

    public function test_v4_cat_fails_on_low_theta_and_stores_scaled_score(): void
    {
        $fixture = $this->track(3, 2.0);
        $this->pool($fixture['session']->id, [0.0, 0.0, 0.0], 1.0);

        $this->actingAs($this->student)->post("/passimark/session/{$fixture['session']->id}/start", ['mode' => 'cat']);
        $attempt = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $fixture['session']->id)->firstOrFail();

        $answered = 0;
        while ($question = CatEngine::nextQuestion($attempt->fresh())) {
            $this->actingAs($this->student)->postJson("/passimark/attempt/{$attempt->id}/answer", [
                'question_id' => $question->id, 'selected' => 'b',
            ])->assertOk();
            $answered++;
        }

        $this->assertSame(3, $answered);
        $attempt->refresh();
        $this->assertTrue($attempt->finished_at !== null);
        $this->assertFalse((bool) $attempt->is_passed);
        $this->assertLessThan(2.0, $attempt->theta);
        $this->assertLessThan(50.0, $attempt->score);
        $this->assertSame('open', PassimarkProgress::where('user_id', $this->student->id)->where('session_id', $fixture['session']->id)->value('status'));
    }

    public function test_v4_cat_early_stops_inside_an_open_band_when_theta_is_confident(): void
    {
        $fixture = $this->track(30, 0.0, min: 15);
        $this->pool($fixture['session']->id, array_fill(0, 30, 0.0), 2.5);

        $this->actingAs($this->student)->post("/passimark/session/{$fixture['session']->id}/start", ['mode' => 'cat']);
        $attempt = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $fixture['session']->id)->firstOrFail();

        $attempted = 0;
        for ($turn = 0; $turn < 15; $turn++) {
            $question = CatEngine::nextQuestion($attempt->fresh());
            $this->assertNotNull($question);
            $this->actingAs($this->student)->postJson("/passimark/attempt/{$attempt->id}/answer", [
                'question_id' => $question->id, 'selected' => $turn === 14 ? 'b' : 'a',
            ])->assertOk();
            $attempted++;
        }

        $attempt->refresh();
        $this->assertNotNull($attempt->finished_at);
        $this->assertLessThan(30, $attempted);
        $this->assertSame(15, $attempt->answers()->count());
        $this->assertTrue((bool) $attempt->is_passed);
    }

    public function test_legacy_cat_keeps_nearest_difficulty_and_percentage_scoring(): void
    {
        $fixture = $this->track(3, null, irt: false);
        $this->pool($fixture['session']->id, [0.0, 0.0, 0.0], 1.0);

        $this->actingAs($this->student)->post("/passimark/session/{$fixture['session']->id}/start", ['mode' => 'cat']);
        $attempt = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $fixture['session']->id)->firstOrFail();
        $this->assertFalse(CatEngine::usesIrt($attempt));

        $answered = 0;
        while ($question = CatEngine::nextQuestion($attempt->fresh())) {
            $this->actingAs($this->student)->postJson("/passimark/attempt/{$attempt->id}/answer", [
                'question_id' => $question->id, 'selected' => 'a',
            ])->assertOk();
            $answered++;
        }

        $this->assertSame(3, $answered);
        $attempt->refresh();
        $this->assertNotNull($attempt->finished_at);
        $this->assertEquals(100, $attempt->score);
        $this->assertTrue((bool) $attempt->is_passed);
    }

    /** Build a v4 ladder: lesson 1 (with cat exam) → lesson 2 (for unlock assertions). */
    private function track(int $questionCount, ?float $thetaRequired = 0.0, bool $irt = true, int $min = 0): array
    {
        $track = PassimarkCertificationTrack::create(['slug' => 'irt-v4', 'title' => 'IRT v4', 'region' => 'TEST', 'advancement' => 'auto']);

        $session = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => 'irtv4', 'number' => 1, 'phase' => 1,
            'phase_type' => 'lesson', 'title' => 'IRT lesson 1', 'order' => 1, 'is_open' => true,
            'theta_required' => $thetaRequired, 'questions_target' => $questionCount,
        ]);
        $next = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => 'irtv4', 'number' => 2, 'phase' => 1,
            'phase_type' => 'lesson', 'title' => 'IRT lesson 2', 'order' => 2, 'is_open' => false,
        ]);

        $exam = PassimarkExam::create([
            'session_id' => $session->id, 'title' => 'IRT v4 CAT', 'mode' => 'cat',
            'question_count' => $questionCount, 'min_questions' => $min ?: $questionCount,
            'max_questions' => $questionCount, 'irt_enabled' => $irt,
        ]);

        PassimarkProgress::create(['user_id' => $this->student->id, 'session_id' => $session->id, 'status' => 'open', 'attempts' => 0]);

        return ['session' => $session, 'next' => $next, 'exam' => $exam];
    }

    /** @param list<float> $difficulties */
    private function pool(int $sessionId, array $difficulties, float $a): array
    {
        $created = [];
        foreach ($difficulties as $i => $b) {
            $created[] = $this->question(sessionId: $sessionId, b: $b, a: $a, questionNumber: $i + 1);
        }
        return $created;
    }

    private function question(int $sessionId, float $b, float $a, int $questionNumber = 1): PassimarkQuestion
    {
        return PassimarkQuestion::create([
            'session_id' => $sessionId, 'content' => "Q{$questionNumber}: IRT item b={$b} a={$a}",
            'options' => [
                ['key' => 'a', 'text' => 'Correct', 'is_correct' => true],
                ['key' => 'b', 'text' => 'Wrong', 'is_correct' => false],
            ],
            'correct_key' => 'a', 'difficulty' => $b, 'discrimination' => $a, 'guessing' => 0.25,
            'domain' => 'TEST', 'bloom_level' => 'remember',
        ]);
    }
}