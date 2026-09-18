<?php

namespace Tests\Feature;

use App\Models\{PassimarkAttempt, PassimarkCertificationTrack, PassimarkExam, PassimarkProgress, PassimarkQuestion, PassimarkSession, User};
use App\Services\CatEngine;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CertificateIssuanceTest extends TestCase
{
    private User $student;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->student = User::create(['name' => 'Ada Learner', 'email' => 'ada@example.com', 'password' => Hash::make('password'), 'role' => 'student']);
        $this->admin = User::create(['name' => 'Instructor', 'email' => 'instructor@example.com', 'password' => Hash::make('password'), 'role' => 'admin']);
    }

    public function test_auto_track_final_pass_issues_a_verifiable_credential(): void
    {
        [$track, $session, $progress] = $this->finalTrack('auto-cert', 'auto');
        $this->passFinal($session);

        $progress->refresh();
        $this->assertSame('completed', $progress->status);
        $this->assertNotNull($progress->certified_at);
        $this->assertMatchesRegularExpression('/^PMK-AUTOCERT-\d{4}-[A-F0-9]{8}$/', $progress->credential_id);
        $this->assertGreaterThan(0.5, $progress->pass_probability);
        $this->assertStringStartsWith('sha256:', $progress->credential_hash);

        // The owner can open the certificate screen.
        $page = $this->actingAs($this->student)->get("/certificate/{$progress->id}")->assertOk()->viewData('page');
        $this->assertSame('Passimark/Certificate', $page['component']);
        $this->assertSame($progress->credential_id, $page['props']['certificate']['credential_id']);
        $this->assertSame($this->student->name, $page['props']['user']['name']);

        // A different user cannot open someone else's certificate.
        $this->actingAs($this->admin)->get("/certificate/{$progress->id}")->assertNotFound();
    }

    public function test_public_verify_resolves_issued_credentials_and_rejects_unknown_codes(): void
    {
        [$track, $session, $progress] = $this->finalTrack('verify-cert', 'auto');
        $this->passFinal($session);
        $progress->refresh();

        $page = $this->get("/verify/{$progress->credential_id}")->assertOk()->viewData('page');
        $this->assertSame('Passimark/Verify', $page['component']);
        $this->assertTrue($page['props']['valid']);
        $this->assertSame('Ada Learner', $page['props']['certificate']['holder']);
        $this->assertSame($progress->credential_id, $page['props']['certificate']['credential_id']);

        $bad = $this->get('/verify/PMK-NOPE-2026-DEADBEEF')->assertOk()->viewData('page');
        $this->assertFalse($bad['props']['valid']);
        $this->assertNull($bad['props']['certificate']);
    }

    public function test_approval_track_final_certifies_only_after_instructor_approval(): void
    {
        [$track, $session, $progress] = $this->finalTrack('approval-cert', 'approval');
        $this->passFinal($session);

        $progress->refresh();
        $this->assertSame('completed', $progress->status);
        $this->assertNull($progress->certified_at, 'approval-gated final must not certify on a pass');

        $this->actingAs($this->student)->post("/passimark/session/{$session->id}/request-approval")->assertRedirect();
        $this->assertSame('pending_approval', $progress->fresh()->status);

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/passimark/progress/{$progress->id}/approve", ['note' => 'Approved.'])
            ->assertOk()
            ->assertJsonPath('status', 'approved');
        $this->assertNotNull($response->json('certificate.credential_id'));

        $progress->refresh();
        $this->assertSame('approved', $progress->status);
        $this->assertNotNull($progress->certified_at);
        $this->assertMatchesRegularExpression('/^PMK-APPROVALCERT-\d{4}-[A-F0-9]{8}$/', $progress->credential_id);
    }

    public function test_pwa_manifest_matches_the_slate_theme(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertSame('#0F172A', strtoupper($manifest['background_color']));
        $this->assertSame('standalone', $manifest['display']);
    }

    /**
     * A track with a single final session, its CAT exam, a small easy pool, and an open progress row.
     *
     * @return array{0: PassimarkCertificationTrack, 1: PassimarkSession, 2: PassimarkProgress}
     */
    private function finalTrack(string $slug, string $advancement, float $thetaRequired = 0.0, int $count = 5): array
    {
        $track = PassimarkCertificationTrack::create([
            'slug' => $slug, 'cert_key' => $slug, 'title' => strtoupper($slug),
            'region' => 'TEST', 'advancement' => $advancement,
        ]);
        $session = PassimarkSession::create([
            'certification_track_id' => $track->id, 'cert_slug' => $slug, 'number' => 1, 'phase' => 4,
            'phase_type' => 'final', 'title' => $slug.' final', 'order' => 1, 'is_open' => true,
            'theta_required' => $thetaRequired, 'pass_score' => 70,
            'question_count' => $count, 'questions_target' => $count,
        ]);
        PassimarkExam::create([
            'session_id' => $session->id, 'title' => 'Final CAT', 'mode' => 'cat',
            'question_count' => $count, 'min_questions' => $count, 'max_questions' => $count,
            'irt_enabled' => true, 'is_final' => true,
        ]);
        $progress = PassimarkProgress::create([
            'user_id' => $this->student->id, 'session_id' => $session->id, 'status' => 'open', 'attempts' => 0,
        ]);

        for ($i = 0; $i < $count; $i++) {
            PassimarkQuestion::create([
                'session_id' => $session->id, 'content' => 'Final Q'.($i + 1),
                'options' => [
                    ['key' => 'a', 'text' => 'Correct', 'is_correct' => true],
                    ['key' => 'b', 'text' => 'Wrong', 'is_correct' => false],
                    ['key' => 'c', 'text' => 'Wrong', 'is_correct' => false],
                    ['key' => 'd', 'text' => 'Wrong', 'is_correct' => false],
                ],
                'correct_key' => 'a', 'difficulty' => -1.0, 'discrimination' => 2.0, 'guessing' => 0.25,
                'domain' => 'TEST', 'bloom_level' => 'apply',
            ]);
        }

        return [$track, $session, $progress];
    }

    /** Answer every item correctly; the CAT engine finishes the attempt itself on pool exhaustion. */
    private function passFinal(PassimarkSession $session): PassimarkAttempt
    {
        $this->actingAs($this->student)->post("/passimark/session/{$session->id}/start", ['mode' => 'cat'])->assertRedirect();
        $attempt = PassimarkAttempt::where('user_id', $this->student->id)->where('session_id', $session->id)->firstOrFail();

        $guard = 0;
        while (($question = CatEngine::nextQuestion($attempt->fresh())) && $guard++ < 50) {
            $this->actingAs($this->student)
                ->postJson("/passimark/attempt/{$attempt->id}/answer", ['question_id' => $question->id, 'selected' => 'a'])
                ->assertOk();
        }

        return $attempt->fresh();
    }
}
