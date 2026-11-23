<?php

namespace Tests\Feature;

use App\Models\PassimarkAttempt;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\User;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileErgonomicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    public function test_candidate_can_persist_touch_ergonomics_session_state(): void
    {
        $user = $this->student();
        $session = PassimarkSession::firstOrFail();
        $exam = PassimarkExam::where('session_id', $session->id)->first() ?? PassimarkExam::firstOrFail();

        $attempt = PassimarkAttempt::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'exam_id' => $exam->id,
            'mode' => 'practice',
            'theta' => 0.0,
            'theta_se' => 1.0,
            'score' => 0.0,
            'is_passed' => false,
            'started_at' => now(),
        ]);

        $payload = [
            'remaining_seconds' => 1800,
            'session_state' => [
                'current_index' => 4,
                'flagged' => [1, 3, 5],
                'strikes' => [
                    '10' => ['A', 'C'],
                ],
                'notes' => 'Candidate exhibit notation on mobile tablet',
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson("/passimark/attempt/{$attempt->id}/state", $payload);

        $response->assertOk();
        $response->assertJson([
            'status' => 'saved',
        ]);

        $attempt->refresh();
        $this->assertEquals(1800, $attempt->remaining_seconds);
        $this->assertIsArray($attempt->session_state);
        $this->assertEquals(4, $attempt->session_state['current_index']);
        $this->assertEquals([1, 3, 5], $attempt->session_state['flagged']);
        $this->assertEquals('Candidate exhibit notation on mobile tablet', $attempt->session_state['notes']);
    }

    public function test_candidate_can_pause_and_resume_assessment_lifecycle(): void
    {
        $user = $this->student();
        $session = PassimarkSession::firstOrFail();
        $exam = PassimarkExam::where('session_id', $session->id)->first() ?? PassimarkExam::firstOrFail();

        $attempt = PassimarkAttempt::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'exam_id' => $exam->id,
            'mode' => 'timed',
            'theta' => 0.0,
            'theta_se' => 1.0,
            'score' => 0.0,
            'is_passed' => false,
            'started_at' => now(),
            'paused_at' => null,
        ]);

        // Pause
        $pauseResponse = $this->actingAs($user)
            ->postJson("/passimark/attempt/{$attempt->id}/pause", [
                'remaining_seconds' => 950,
                'session_state' => ['current_index' => 2, 'flagged' => [2]],
            ]);

        $pauseResponse->assertOk();
        $pauseResponse->assertJson(['status' => 'paused', 'remaining_seconds' => 950]);

        $attempt->refresh();
        $this->assertNotNull($attempt->paused_at);
        $this->assertEquals(950, $attempt->remaining_seconds);

        // Resume
        $resumeResponse = $this->actingAs($user)
            ->postJson("/passimark/attempt/{$attempt->id}/resume");

        $resumeResponse->assertOk();
        $resumeResponse->assertJson(['status' => 'resumed', 'remaining_seconds' => 950]);

        $attempt->refresh();
        $this->assertNull($attempt->paused_at);
    }
}
