<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkSession;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Services\CatEngine;
use Database\Seeders\CISSPBundleSeeder;
use Tests\TestCase;

class CISSPBundleSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->seed(CISSPBundleSeeder::class);
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    public function cisspSession(int $number): PassimarkSession
    {
        return PassimarkSession::where('number', $number)->firstOrFail();
    }

    public function test_bundle_seeding_creates_the_full_textbook_curriculum(): void
    {
        $this->assertDatabaseHas('passimark_certification_tracks', ['slug' => 'cissp']);

        // 46 sessions (45-day plan + pacing/summary), one phase-1 lesson open for the demo student only.
        $this->assertSame(46, PassimarkSession::count());
        $this->assertSame(1, PassimarkSession::where('is_open', true)->count());
        $this->assertTrue(PassimarkSession::where('number', 1)->value('is_open'));
        $this->assertDatabaseHas('passimark_progress', [
            'user_id' => $this->student()->id,
            'session_id' => $this->cisspSession(1)->id,
            'status' => 'open',
        ]);

        // Full assessment bank: daily drills (15 q), benchmark (50), simulated CAT + 2 mocks (125
        // each), plus 3 x 15 optional remediation pools generated for narrative-only sessions.
        $this->assertSame(1025, PassimarkQuestion::count());
        $cases = [
            1 => 15, 15 => 50, 30 => 125, 40 => 125, 42 => 125,
            23 => 15, 41 => 15, 43 => 15, 44 => 15, 45 => 0, 46 => 0,
        ];
        foreach ($cases as $number => $expected) {
            $this->assertSame($expected, $this->cisspSession($number)->questions()->count(), "session {$number} pool");
        }

        // Remediation sessions are optional practice — startable, but never required for the ladder.
        foreach ([41, 43, 44] as $number) {
            $this->assertTrue($this->cisspSession($number)->is_optional, "session {$number} optional");
        }
        $this->assertFalse($this->cisspSession(42)->is_optional);

        // Every pool session gets all three exam modes sized to the pool.
        $poolSessions = PassimarkSession::has('questions')->count();
        $this->assertSame(44, $poolSessions);
        $this->assertSame($poolSessions * 3, PassimarkExam::count());

        // The 23rd textbook session headers are mislabelled "19.x" in the source; extraction must still map them.
        $this->assertStringContainsString('SDLC', PassimarkSession::where('number', 23)->value('title'));
    }

    public function test_every_question_is_well_formed_and_tagged(): void
    {
        foreach (PassimarkQuestion::with('tags')->get() as $q) {
            $options = collect($q->options);
            $this->assertCount(4, $options);
            $this->assertSame(1, $options->where('is_correct', true)->count());
            $this->assertNotEmpty($q->correct_key);
            $this->assertContains($q->correct_key, $options->pluck('key')->all());
            $this->assertNotEmpty($q->content);
            $this->assertNotEmpty($q->explanation);
            $types = $q->tags->pluck('type')->unique();
            $this->assertContains('domain', $types);
            $this->assertContains('bloom', $types);
        }
    }

    /**
     * The textbook content is authentic; the psychometric calibration was not.
     *
     * This seeder used to write difficulty = -0.6 + 0.30*(phase-1) + 0.45*(i/(n-1)), a fixed
     * discrimination of 1.2 and a fixed 0.25 guessing floor. Those are a formula applied to
     * row order, not measurements, and Irt3PL reads a/b/c off the row, the theta becomes
     * pass_probability, and CertificateIssuer mints on that. Real content should carry honest
     * unknowns rather than invented calibration.
     */
    public function test_no_item_asserts_an_invented_psychometric_parameter(): void
    {
        foreach (PassimarkQuestion::all() as $q) {
            $this->assertNull($q->difficulty, $q->external_id ?: 'question');
            $this->assertNull($q->discrimination, $q->external_id ?: 'question');
            $this->assertNull($q->guessing, $q->external_id ?: 'question');
        }
    }

/**
     * Every item must name a real provenance: the textbook itself, or the textbook bank for
     * the generated remediation pools (which are clones of real items, not new content).
     * What must never appear is a citation to a document that does not exist.
     */
public function test_every_item_cites_its_real_provenance(): void
    {
        foreach (PassimarkQuestion::all() as $q) {
            $reference = (string) $q->reference;

            $this->assertMatchesRegularExpression(
                '/Hexadigitall CISSP Mastery textbook|textbook bank/',
                $reference,
                $q->external_id ?: 'question'
            );
            $this->assertStringNotContainsStringIgnoringCase('official exam guide', $reference);
        }
    }

    public function test_new_student_cannot_start_a_locked_session(): void
    {
        // Sessions beyond #1 have no progress row yet, so the engine treats them as locked (ModelNotFound -> 404).
        $this->actingAs($this->student())
            ->post("/passimark/session/{$this->cisspSession(2)->id}/start")
            ->assertStatus(404);
    }

    public function test_student_can_complete_the_sessions_cat_drill_using_the_extracted_pool(): void
    {
        $student = $this->student();
        $session = $this->cisspSession(1);

        $this->actingAs($student)
            ->post("/passimark/session/{$session->id}/start", ['mode' => 'cat'])
            ->assertRedirect();

        $attempt = PassimarkAttempt::where('user_id', $student->id)->where('session_id', $session->id)->firstOrFail();
        $answered = 0;
        $question = CatEngine::nextQuestion($attempt);
        while ($question) {
            $correct = collect($question->options)->firstWhere('is_correct', true);
            $this->actingAs($student)
                ->post("/passimark/attempt/{$attempt->id}/answer", [
                    'question_id' => $question->id,
                    'selected' => $correct['key'],
                ])
                ->assertOk();
            $answered++;
            $question = CatEngine::nextQuestion($attempt->fresh());
        }
        // Pool is 15; the engine terminates once the pool is exhausted.
        $this->actingAs($student)
            ->post("/passimark/attempt/{$attempt->id}/finish")
            ->assertOk();

        $this->assertSame(15, $answered);
        $this->assertNotNull($attempt->fresh()->finished_at);
        $progress = PassimarkProgress::where('user_id', $student->id)->where('session_id', $session->id)->first();
        $this->assertSame('completed', $progress->status);
    }

    public function test_bundle_seeder_is_idempotent(): void
    {
        $this->seed(CISSPBundleSeeder::class);

        $this->assertSame(46, PassimarkSession::count());
        $this->assertSame(1025, PassimarkQuestion::count());
        $this->assertSame(44 * 3, PassimarkExam::count());
    }
}
