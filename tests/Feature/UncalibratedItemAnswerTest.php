<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use Database\Seeders\PassimarkSeeder;
use Tests\TestCase;

/**
 * Authored bundles deliberately carry no measured item parameters
 * (docs/content-format.md), so every question row has a null difficulty,
 * discrimination and guessing. The IRT path normalises those through
 * Irt3PL::params(); the legacy CAT path used to hand them straight to a float
 * parameter and 500 on the first answer, which is what blanked the exam page.
 */
class UncalibratedItemAnswerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed(PassimarkSeeder::class);
        $this->student = User::where('email', 'student@passimark.com')->firstOrFail();
    }

    private User $student;

    public function test_answering_an_item_without_measured_parameters_does_not_error(): void
    {
        $fixture = $this->fixture(irtEnabled: false);
        $question = $this->uncalibratedQuestion($fixture['session']->id);

        $this->actingAs($this->student)->postJson("/passimark/attempt/{$fixture['attempt']->id}/answer", [
            'question_id' => $question->id,
            'selected' => 'a',
        ])->assertOk();

        $attempt = $fixture['attempt']->fresh();
        $this->assertSame(1, $attempt->answers()->count());
        $this->assertNotSame(0.0, (float) $attempt->theta);
    }

    public function test_legacy_cat_scoring_falls_back_to_the_documented_irt_defaults(): void
    {
        $fixture = $this->fixture(irtEnabled: false);
        $question = $this->uncalibratedQuestion($fixture['session']->id);

        $this->actingAs($this->student)->postJson("/passimark/attempt/{$fixture['attempt']->id}/answer", [
            'question_id' => $question->id,
            'selected' => 'a',
        ])->assertOk();

        // b = 0.0, a = 1.0, c = 0.25 from Irt3PL::params(); theta starts at 0.
        $p = 0.25 + (1 - 0.25) / (1 + exp(-1.0 * (0.0 - 0.0)));
        $expected = 0.35 * 1.0 * (1 - $p);

        $this->assertEqualsWithDelta($expected, (float) $fixture['attempt']->fresh()->theta, 0.000001);
    }

    public function test_the_irt_path_scores_the_same_uncalibrated_item_without_error(): void
    {
        $fixture = $this->fixture(irtEnabled: true);
        $question = $this->uncalibratedQuestion($fixture['session']->id);

        $this->actingAs($this->student)->postJson("/passimark/attempt/{$fixture['attempt']->id}/answer", [
            'question_id' => $question->id,
            'selected' => 'a',
        ])->assertOk();

        $this->assertSame(1, $fixture['attempt']->fresh()->answers()->count());
    }

    public function test_starting_a_cat_attempt_creates_an_exam_that_runs_the_irt_engine(): void
    {
        $track = PassimarkCertificationTrack::where('slug', 'cissp')->firstOrFail();
        $session = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => 'cissp', 'number' => 90, 'phase' => 1,
            'phase_type' => 'lesson', 'title' => 'Runtime exam lesson', 'order' => 90, 'is_open' => true,
        ]);
        PassimarkProgress::create([
            'user_id' => $this->student->id, 'session_id' => $session->id, 'status' => 'open', 'attempts' => 0,
        ]);

        $this->actingAs($this->student)->post("/passimark/session/{$session->id}/start", ['mode' => 'cat'])
            ->assertRedirect();

        $exam = PassimarkExam::where('session_id', $session->id)->where('mode', 'cat')->firstOrFail();
        $this->assertTrue((bool) $exam->irt_enabled, 'a CAT exam must run the v4 IRT engine');
    }

    public function test_seeded_cat_exams_run_the_irt_engine_and_other_modes_do_not(): void
    {
        $this->assertSame(0, PassimarkExam::where('mode', 'cat')->where('irt_enabled', false)->count());
        $this->assertSame(0, PassimarkExam::where('mode', '!=', 'cat')->where('irt_enabled', true)->count());
        $this->assertGreaterThan(0, PassimarkExam::where('mode', 'cat')->where('irt_enabled', true)->count());
    }

    /**
     * A session whose only exam is a legacy (non-IRT) CAT exam, plus one attempt
     * on it - the shape that produced the reported TypeError.
     *
     * @return array{session: PassimarkSession, attempt: PassimarkAttempt}
     */
    private function fixture(bool $irtEnabled): array
    {
        $track = PassimarkCertificationTrack::where('slug', 'cissp')->firstOrFail();
        $session = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => 'cissp', 'number' => 91, 'phase' => 1,
            'phase_type' => 'lesson', 'title' => 'Uncalibrated lesson', 'order' => 91, 'is_open' => true,
        ]);
        $exam = PassimarkExam::create([
            'session_id' => $session->id, 'title' => 'Uncalibrated CAT', 'mode' => 'cat',
            'question_count' => 150, 'irt_enabled' => $irtEnabled,
        ]);
        PassimarkProgress::create([
            'user_id' => $this->student->id, 'session_id' => $session->id, 'status' => 'open', 'attempts' => 0,
        ]);
        $attempt = PassimarkAttempt::create([
            'user_id' => $this->student->id, 'session_id' => $session->id, 'exam_id' => $exam->id,
            'mode' => 'cat', 'theta' => 0, 'started_at' => now(),
        ]);

        return ['session' => $session, 'attempt' => $attempt];
    }

    public function test_a_lazily_created_exam_advertises_only_the_items_its_session_holds(): void
    {
        $track = PassimarkCertificationTrack::where('slug', 'cissp')->firstOrFail();
        $session = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => 'cissp', 'number' => 92, 'phase' => 1,
            'phase_type' => 'lesson', 'title' => 'Short pool lesson', 'order' => 92, 'is_open' => true,
        ]);
        PassimarkProgress::create([
            'user_id' => $this->student->id, 'session_id' => $session->id, 'status' => 'open', 'attempts' => 0,
        ]);
        foreach (range(1, 5) as $index) {
            PassimarkQuestion::create([
                'session_id' => $session->id,
                'content' => "Pool item {$index}",
                'options' => [
                    ['key' => 'A', 'text' => 'Correct', 'is_correct' => true],
                    ['key' => 'B', 'text' => 'Wrong', 'is_correct' => false],
                ],
                'correct_key' => 'A',
                'domain' => 'TEST', 'bloom_level' => 'remember',
            ]);
        }

        $this->actingAs($this->student)->post("/passimark/session/{$session->id}/start", ['mode' => 'cat'])
            ->assertRedirect();

        $exam = PassimarkExam::where('session_id', $session->id)->where('mode', 'cat')->firstOrFail();
        // The 150-question CAT default cannot be served from a 5-item pool, and the
        // exam screen counts against it - a short session used to read "of 150".
        $this->assertSame(5, (int) $exam->question_count, 'a CAT exam cannot promise more items than the session holds');
    }

    private function uncalibratedQuestion(int $sessionId): PassimarkQuestion
    {
        return PassimarkQuestion::create([
            'session_id' => $sessionId,
            'content' => 'Item with no measured parameters.',
            'options' => [
                ['key' => 'a', 'text' => 'Correct', 'is_correct' => true],
                ['key' => 'b', 'text' => 'Wrong', 'is_correct' => false],
            ],
            'correct_key' => 'a',
            'difficulty' => null,
            'discrimination' => null,
            'guessing' => null,
            'domain' => 'TEST', 'bloom_level' => 'remember',
        ]);
    }
}
