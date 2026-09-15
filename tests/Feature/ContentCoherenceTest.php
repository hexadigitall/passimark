<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Models\PassimarkSession;
use Database\Seeders\CISSPBundleSeeder;
use Tests\TestCase;

class ContentCoherenceTest extends TestCase
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

    public function test_bundle_curriculum_has_v4_catalog_metadata(): void
    {
        // Region grouping (Sprint 7) is meaningful for the real-content track now.
        $this->assertDatabaseHas('passimark_certification_tracks', ['slug' => 'cissp', 'region' => 'USA-IT-SECURITY']);

        // Every CAT exam on the real-content track runs the v4 IRT engine.
        $this->assertSame(41, PassimarkExam::where('mode', 'cat')->where('irt_enabled', true)->count());
        $this->assertSame(0, PassimarkExam::where('mode', 'cat')->where('irt_enabled', false)->count());
        $this->assertSame(0, PassimarkExam::where('mode', '!=', 'cat')->where('irt_enabled', true)->count());

        // Phase ladder + gating theta are staged on the real content.
        $this->assertSame('lesson', PassimarkSession::where('number', 1)->value('phase_type'));
        $this->assertSame('mock', PassimarkSession::where('number', 15)->value('phase_type'));
        $this->assertSame('final', PassimarkSession::where('number', 42)->value('phase_type'));
        $this->assertSame(0.5, (float) PassimarkSession::where('number', 42)->value('theta_required'));
        $finalExams = PassimarkExam::where('session_id', PassimarkSession::where('number', 42)->value('id'))->where('is_final', true)->count();
        $this->assertSame(3, $finalExams);

        // Adaptive CAT gets the extended 1.5x time budget (Sprint 7 countdown).
        $lesson = PassimarkSession::where('number', 1)->firstOrFail();
        $this->assertSame(135, $lesson->exams()->where('mode', 'cat')->value('time_minutes'));
        $mock = PassimarkSession::where('number', 40)->firstOrFail();
        $this->assertSame(270, $mock->exams()->where('mode', 'cat')->value('time_minutes'));

        // Region nav (Sprint 7) exposes the track's region.
        $page = $this->actingAs($this->student())->get('/')->assertOk()->viewData('page');
        $regionNames = array_column($page['props']['regions'], 'name');
        $this->assertContains('USA-IT-SECURITY', $regionNames);
    }

    public function test_registration_provisions_enrollment_into_first_lesson(): void
    {
        $this->post('/register', [
            'name' => 'New Candidate',
            'email' => 'candidate@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'candidate@example.com')->firstOrFail();
        $this->assertSame('student', $user->role);

        // The new learner is automatically enrolled at the first assessable lesson.
        $this->assertDatabaseHas('passimark_progress', [
            'user_id' => $user->id,
            'session_id' => PassimarkSession::where('number', 1)->value('id'),
            'status' => 'open',
        ]);

        // And can start it immediately (no 404 dead end).
        $this->actingAs($user)
            ->post('/passimark/session/' . PassimarkSession::where('number', 1)->value('id') . '/start', ['mode' => 'cat'])
            ->assertRedirect();
    }

    public function test_approval_is_idempotent_and_skips_contentless_sessions(): void
    {
        $student = $this->student();
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        // Approve a pending lesson -> next assessable session unlocks.
        $progress = PassimarkProgress::where('user_id', $student->id)
            ->where('session_id', PassimarkSession::where('number', 1)->value('id'))->firstOrFail();
        $progress->update(['status' => PassimarkProgress::PENDING, 'score' => 84]);

        $this->actingAs($admin)
            ->postJson("/admin/passimark/progress/{$progress->id}/approve", ['note' => 'Demonstrated mastery.'])
            ->assertOk()
            ->assertJsonPath('status', 'approved');
        $this->assertDatabaseHas('passimark_progress', [
            'user_id' => $student->id,
            'session_id' => PassimarkSession::where('number', 2)->value('id'),
            'status' => 'open',
        ]);

        // Re-approving the same submission is a no-op, not a 422.
        $this->actingAs($admin)
            ->postJson("/admin/passimark/progress/{$progress->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'skipped')
            ->assertJsonPath('message', 'This submission was already approved.');

        // Unlock skips contentless (0-question) remediation sessions to the next assessable mock.
        $progress40 = PassimarkProgress::firstOrCreate([
            'user_id' => $student->id,
            'session_id' => PassimarkSession::where('number', 40)->value('id'),
        ], ['status' => 'completed']);
        $progress40->update(['status' => PassimarkProgress::PENDING, 'score' => 91]);

        $this->actingAs($admin)
            ->postJson("/admin/passimark/progress/{$progress40->id}/approve")
            ->assertOk()
            ->assertJsonPath('message', 'Approved. Session 42 unlocked');
        $this->assertNotNull(PassimarkProgress::where('user_id', $student->id)
            ->where('session_id', PassimarkSession::where('number', 42)->value('id'))->first());

        // The final has nothing after it -> no further unlock.
        $progress42 = PassimarkProgress::where('user_id', $student->id)
            ->where('session_id', PassimarkSession::where('number', 42)->value('id'))->firstOrFail();
        $progress42->update(['status' => PassimarkProgress::PENDING, 'score' => 88]);
        $this->actingAs($admin)
            ->postJson("/admin/passimark/progress/{$progress42->id}/approve")
            ->assertOk()
            ->assertJsonPath('message', 'Approved. Track completed.');
    }

    public function test_profile_payload_is_data_driven(): void
    {
        $student = $this->student();
        $page = $this->actingAs($student)->get('/profile')->assertOk()->viewData('page');

        $this->assertArrayHasKey('summary', $page['props']);
        $summary = $page['props']['summary'];
        $this->assertSame(1, $summary['current_phase']);
        $this->assertSame(1, $summary['current_session']);
        $this->assertSame(0, $summary['sessions_completed']);
        $this->assertSame(41, $summary['sessions_total']);
        $this->assertSame(1, $summary['tracks']);
        $this->assertArrayHasKey('ability_theta', $summary);
    }

    public function test_settings_are_persisted(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->from('/settings')
            ->patch('/settings', [
                'notifications' => [
                    'session_reminders' => false,
                    'approval_updates' => true,
                    'assessment_deadlines' => true,
                    'weekly_digest' => false,
                ],
                'theme' => 'light',
                'language' => 'es',
            ])
            ->assertRedirect('/settings');

        $student->refresh();
        $preferences = $student->preferences;
        $this->assertFalse($preferences['notifications']['session_reminders']);
        $this->assertTrue($preferences['notifications']['assessment_deadlines']);
        $this->assertSame('light', $preferences['theme']);
        $this->assertSame('es', $preferences['language']);

        // The page renders the persisted preferences back.
        $page = $this->actingAs($student)->get('/settings')->assertOk()->viewData('page');
        $this->assertSame('light', $page['props']['preferences']['theme']);
        $this->assertSame('es', $page['props']['preferences']['language']);
    }

    public function test_global_ladder_uses_irt_finish_theta_gate_on_real_content(): void
    {
        $student = $this->student();
        $session = PassimarkSession::where('number', 1)->firstOrFail();

        $this->actingAs($student)->post("/passimark/session/{$session->id}/start", ['mode' => 'cat'])->assertRedirect();
        $attempt = PassimarkAttempt::where('user_id', $student->id)->where('session_id', $session->id)->firstOrFail();

        $question = \App\Services\CatEngine::nextQuestion($attempt);
        $correct = collect($question->options)->firstWhere('is_correct', true);
        $this->actingAs($student)->post("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'selected' => $correct['key'],
        ])->assertOk();
        $this->assertNotNull($attempt->fresh()->theta, 'v4 IRT must estimate theta on every response');

        $this->actingAs($student)->post("/passimark/attempt/{$attempt->id}/finish")->assertOk();
        $this->assertNotNull($attempt->fresh()->finished_at);
    }
}