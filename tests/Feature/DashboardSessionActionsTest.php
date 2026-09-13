<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkProgress;
use App\Models\PassimarkApprovalEvent;
use App\Models\PassimarkExam;
use App\Models\PassimarkQuestion;
use App\Services\CatEngine;
use Database\Seeders\PassimarkSeeder;
use Tests\TestCase;

class DashboardSessionActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->seed(PassimarkSeeder::class);
    }

    public function test_student_dashboard_displays_start_session_actions_for_open_sessions(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $response = $this->actingAs($student)->get('/');

        $response->assertOk();
        $this->assertDatabaseHas('passimark_progress', [
            'user_id' => $student->id,
            'session_id' => 1,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('passimark_sessions', [
            'id' => 1,
            'title' => 'Session 1 • Security Governance & Frameworks',
        ]);
    }

    public function test_invalid_credentials_return_validation_errors(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'student@passimark.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
    }

    public function test_authenticated_user_can_view_profile_and_settings_pages(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)->get('/profile')->assertOk();
        $this->actingAs($student)->get('/settings')->assertOk();
    }

    public function test_student_can_start_open_session_and_reach_exam(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $response = $this->actingAs($student)->post('/passimark/session/1/start', ['mode' => 'cat']);

        $attempt = PassimarkAttempt::where('user_id', $student->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('passimark.exam', ['attempt' => $attempt]));
        $this->actingAs($student)->get(route('passimark.exam', ['attempt' => $attempt]))->assertOk();
        $this->assertDatabaseHas('passimark_progress', ['user_id' => $student->id, 'session_id' => 1, 'status' => 'in_progress']);
    }

    public function test_student_progress_and_sessions_api_are_scoped_and_filterable(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $sessions = $this->actingAs($student)->getJson('/api/sessions?phase=1&status=open');
        $sessions->assertOk()->assertJsonPath('data.0.progress.status', 'open');
        $this->actingAs($student)->getJson('/api/progress')
            ->assertOk()
            ->assertJsonPath('data.sessions_completed', 0)
            ->assertJsonPath('data.current_phase', 1);
    }

    public function test_student_can_complete_a_practice_attempt_and_receive_a_result(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $this->actingAs($student)->post('/passimark/session/1/start', ['mode' => 'practice']);
        $attempt = PassimarkAttempt::where('user_id', $student->id)->where('mode', 'practice')->latest('id')->firstOrFail();

        for ($index = 0; $index < 25; $index++) {
            $question = CatEngine::nextQuestion($attempt->fresh());
            $this->assertNotNull($question);
            $response = $this->actingAs($student)->postJson("/passimark/attempt/{$attempt->id}/answer", [
                'question_id' => $question->id,
                'selected' => $question->options[0]['key'],
                'time_spent' => 4,
            ]);
            $response->assertOk();
        }

        $attempt->refresh();
        $this->assertNotNull($attempt->finished_at);
        $this->assertNotNull($attempt->score);
        $this->assertDatabaseHas('passimark_progress', [
            'user_id' => $student->id,
            'session_id' => 1,
            'attempts' => 1,
        ]);
        $this->actingAs($student)->get(route('passimark.result', ['attempt' => $attempt]))->assertOk();
    }

    public function test_student_cannot_access_admin_workflow_and_invalid_answer_is_rejected(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $this->actingAs($student)->get('/admin/passimark')->assertForbidden();
        $this->actingAs($student)->post('/passimark/session/1/start', ['mode' => 'practice']);
        $attempt = PassimarkAttempt::where('user_id', $student->id)->where('mode', 'practice')->latest('id')->firstOrFail();
        $question = CatEngine::nextQuestion($attempt);

        $this->actingAs($student)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'selected' => 'INVALID',
        ])->assertStatus(422);
    }

    public function test_admin_can_review_pending_progress_and_unlock_next_session(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $progress = PassimarkProgress::where('user_id', $student->id)->where('session_id', 1)->firstOrFail();
        $progress->update(['status' => PassimarkProgress::PENDING, 'score' => 82]);

        $this->actingAs($admin)->get('/admin/passimark')->assertOk();
        $this->actingAs($admin)->postJson("/admin/passimark/progress/{$progress->id}/approve", ['note' => 'Strong evidence of mastery.'])
            ->assertOk()
            ->assertJsonPath('message', 'Approved. Session 2 unlocked');
        $this->assertDatabaseHas('passimark_progress', ['id' => $progress->id, 'status' => 'approved']);
        $this->assertDatabaseHas('passimark_progress', ['user_id' => $student->id, 'session_id' => 2, 'status' => 'open']);
        $this->assertDatabaseHas('passimark_approval_events', ['progress_id' => $progress->id, 'reviewer_id' => $admin->id, 'action' => 'approved', 'note' => 'Strong evidence of mastery.']);
    }

    public function test_admin_can_crud_content_and_cannot_assign_exam_across_sessions(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $payload = ['certification_track_id' => 1, 'number' => 99, 'phase' => 4, 'title' => 'CRUD test session', 'order' => 99, 'domain' => 'Testing'];
        $sessionResponse = $this->actingAs($admin)->postJson('/admin/sessions', $payload)->assertCreated();
        $sessionId = $sessionResponse->json('data.id');
        $this->actingAs($admin)->putJson("/admin/sessions/{$sessionId}", ['title' => 'Updated test session'])->assertOk();

        $examResponse = $this->actingAs($admin)->postJson('/admin/exams', [
            'session_id' => $sessionId, 'title' => 'CRUD test exam', 'mode' => 'practice', 'question_count' => 2,
        ])->assertCreated();
        $examId = $examResponse->json('data.id');
        $question = ['session_id' => $sessionId, 'exam_id' => $examId, 'content' => 'Which option is correct?', 'domain' => 'Testing', 'options' => [
            ['key' => 'A', 'text' => 'Correct', 'is_correct' => true], ['key' => 'B', 'text' => 'Incorrect', 'is_correct' => false],
        ]];
        $questionResponse = $this->actingAs($admin)->postJson('/admin/questions', $question)->assertCreated();
        $questionId = $questionResponse->json('data.id');
        $this->actingAs($admin)->putJson("/admin/questions/{$questionId}", ['content' => 'Updated question'])->assertOk();

        $this->actingAs($admin)->postJson('/admin/questions', array_merge($question, ['session_id' => 2]))->assertStatus(422);
        $this->actingAs($admin)->deleteJson("/admin/questions/{$questionId}")->assertNoContent();
        $this->actingAs($admin)->deleteJson("/admin/exams/{$examId}")->assertNoContent();
        $this->actingAs($admin)->deleteJson("/admin/sessions/{$sessionId}")->assertNoContent();
        $this->assertDatabaseMissing('passimark_questions', ['id' => $questionId]);
    }

    public function test_admin_dashboard_includes_reporting_metrics(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        $this->actingAs($admin)->get('/admin/passimark')->assertOk();
        $this->assertSame(1, User::where('role', 'student')->count());
        $this->assertSame(0, PassimarkAttempt::count());
    }

    public function test_admin_can_import_validated_question_batches(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $questions = [[
            'content' => 'Which control protects confidentiality?',
            'domain' => 'Testing',
            'options' => [
                ['key' => 'A', 'text' => 'Encryption', 'is_correct' => true],
                ['key' => 'B', 'text' => 'CCTV', 'is_correct' => false],
            ],
        ]];

        $this->actingAs($admin)->get('/admin/import')->assertOk();
        $this->actingAs($admin)->postJson('/admin/passimark/questions/import', ['session_id' => 1, 'questions' => $questions])
            ->assertOk()->assertJsonPath('imported', 1);
        $this->assertDatabaseHas('passimark_questions', ['session_id' => 1, 'content' => 'Which control protects confidentiality?']);
        $this->actingAs($admin)->postJson('/admin/passimark/questions/import', ['session_id' => 1, 'questions' => [[...$questions[0], 'options' => [['key' => 'A', 'text' => 'No correct answer', 'is_correct' => false], ['key' => 'B', 'text' => 'Also wrong', 'is_correct' => false]]]]])->assertStatus(422);
    }

    public function test_requesting_approval_flashes_a_success_message_for_the_dashboard_banner(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $progress = PassimarkProgress::where('user_id', $student->id)->where('session_id', 1)->firstOrFail();
        $progress->update(['status' => 'completed', 'score' => 75]);

        $response = $this->actingAs($student)->post('/passimark/session/1/request-approval');

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Approval requested. Instructor will unlock next session.');
    }

    public function test_admin_can_create_a_second_certification_track_and_scope_a_session_to_it(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        $this->assertDatabaseHas('passimark_certification_tracks', ['slug' => 'cissp']);
        $this->assertDatabaseHas('passimark_sessions', ['id' => 1, 'certification_track_id' => 1]);

        $trackResponse = $this->actingAs($admin)->postJson('/admin/certification-tracks', [
            'slug' => 'security-plus', 'title' => 'Security+', 'description' => 'CompTIA Security+ preparation track.',
        ])->assertCreated();
        $trackId = $trackResponse->json('data.id');

        $sessionResponse = $this->actingAs($admin)->postJson('/admin/sessions', [
            'certification_track_id' => $trackId, 'number' => 1, 'phase' => 1, 'title' => 'Security+ Session 1', 'order' => 200,
        ])->assertCreated();
        $sessionId = $sessionResponse->json('data.id');

        $this->assertDatabaseHas('passimark_sessions', ['id' => $sessionId, 'certification_track_id' => $trackId]);
        $this->actingAs($admin)->deleteJson("/admin/certification-tracks/{$trackId}")->assertStatus(422);
        $this->actingAs($admin)->deleteJson("/admin/sessions/{$sessionId}")->assertNoContent();
        $this->actingAs($admin)->deleteJson("/admin/certification-tracks/{$trackId}")->assertNoContent();
    }
}
