<?php

namespace Tests\Feature;

use App\Models\PassimarkAttempt;
use App\Models\PassimarkAttemptAnswer;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\User;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileSyncTest extends TestCase
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

    public function test_download_offline_question_bank_returns_active_tracks_sessions_and_questions(): void
    {
        $response = $this->actingAs($this->student())
            ->getJson('/api/mobile/sync/download');

        $response->assertOk();
        $response->assertJsonStructure([
            'version',
            'timestamp',
            'tracks',
            'sessions',
            'exams',
            'questions',
            'progress',
        ]);

        $this->assertNotEmpty($response->json('tracks'));
        $this->assertNotEmpty($response->json('sessions'));
        $this->assertNotEmpty($response->json('questions'));

        $firstQuestion = $response->json('questions.0');
        $this->assertArrayHasKey('id', $firstQuestion);
        $this->assertArrayHasKey('content', $firstQuestion);
        $this->assertArrayHasKey('type', $firstQuestion);
        $this->assertArrayHasKey('difficulty', $firstQuestion);
    }

    public function test_upload_offline_completed_attempt_syncs_answers_and_updates_progress(): void
    {
        $user = $this->student();
        $session = PassimarkSession::firstOrFail();
        $exam = PassimarkExam::where('session_id', $session->id)->first() ?? PassimarkExam::firstOrFail();
        $questions = PassimarkQuestion::where('session_id', $session->id)->take(2)->get();

        $startedAt = Carbon::now()->subMinutes(15)->toRfc3339String();
        $completedAt = Carbon::now()->toRfc3339String();
        $localId = 'local_att_' . (string) Str::uuid();

        $payload = [
            'attempts' => [
                [
                    'local_id' => $localId,
                    'session_id' => $session->id,
                    'exam_id' => $exam->id,
                    'mode' => 'cat',
                    'theta' => 0.85,
                    'theta_se' => 0.22,
                    'score' => 90.0,
                    'passed' => true,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'time_spent' => 900,
                    'answers' => [
                        [
                            'question_id' => $questions[0]->id,
                            'is_correct' => true,
                            'time_spent' => 45,
                            'interaction_response' => ['selected' => 'A'],
                        ],
                        [
                            'question_id' => $questions[1]->id,
                            'is_correct' => true,
                            'time_spent' => 55,
                            'interaction_response' => ['selected' => 'B'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/mobile/sync/upload', $payload);

        $response->assertOk();
        $response->assertJson([
            'synced_count' => 1,
            'attempts' => [
                [
                    'local_id' => $localId,
                    'status' => 'synced',
                ],
            ],
        ]);

        // Verify attempt persisted
        $attempt = PassimarkAttempt::where('user_id', $user->id)
            ->where('session_state->local_id', $localId)
            ->firstOrFail();

        $this->assertNotNull($attempt->finished_at);
        $this->assertEquals(90.0, $attempt->score);
        $this->assertTrue((bool) $attempt->is_passed);
        $this->assertSame(900, $attempt->session_state['time_spent']);

        // Verify answers persisted
        $this->assertSame(2, PassimarkAttemptAnswer::where('attempt_id', $attempt->id)->count());

        // Verify progress updated
        $progress = PassimarkProgress::where('user_id', $user->id)
            ->where('session_id', $session->id)
            ->firstOrFail();

        $this->assertSame('completed', $progress->status);
        $this->assertEquals(90, $progress->score);
    }

    public function test_upload_offline_attempt_is_strictly_idempotent(): void
    {
        $user = $this->student();
        $session = PassimarkSession::firstOrFail();
        $question = PassimarkQuestion::where('session_id', $session->id)->firstOrFail();
        $localId = 'local_att_idempotent_' . (string) Str::uuid();

        $payload = [
            'attempts' => [
                [
                    'local_id' => $localId,
                    'session_id' => $session->id,
                    'mode' => 'timed',
                    'score' => 80.0,
                    'passed' => true,
                    'started_at' => Carbon::now()->subMinutes(10)->toRfc3339String(),
                    'completed_at' => Carbon::now()->toRfc3339String(),
                    'time_spent' => 600,
                    'answers' => [
                        [
                            'question_id' => $question->id,
                            'is_correct' => true,
                            'time_spent' => 30,
                        ],
                    ],
                ],
            ],
        ];

        // 1. First sync
        $res1 = $this->actingAs($user)->postJson('/api/mobile/sync/upload', $payload);
        $res1->assertOk();
        $res1->assertJsonPath('attempts.0.status', 'synced');

        $initialAttemptCount = PassimarkAttempt::count();

        // 2. Second sync with identical local_id (replay)
        $res2 = $this->actingAs($user)->postJson('/api/mobile/sync/upload', $payload);
        $res2->assertOk();
        $res2->assertJsonPath('attempts.0.status', 'already_synced');

        // Zero duplicate attempt rows created
        $this->assertSame($initialAttemptCount, PassimarkAttempt::count());
    }

    public function test_upload_offline_attempt_verifies_clock_tampering(): void
    {
        $user = $this->student();
        $session = PassimarkSession::firstOrFail();
        $question = PassimarkQuestion::where('session_id', $session->id)->firstOrFail();
        $localId = 'local_att_tamper_' . (string) Str::uuid();

        // Deliberately corrupt completed_at to precede started_at
        $startedAt = Carbon::now()->toRfc3339String();
        $corruptCompletedAt = Carbon::now()->subHours(2)->toRfc3339String();

        $payload = [
            'attempts' => [
                [
                    'local_id' => $localId,
                    'session_id' => $session->id,
                    'mode' => 'practice',
                    'score' => 100.0,
                    'passed' => true,
                    'started_at' => $startedAt,
                    'completed_at' => $corruptCompletedAt,
                    'time_spent' => 300,
                    'answers' => [
                        [
                            'question_id' => $question->id,
                            'is_correct' => true,
                            'time_spent' => 20,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/mobile/sync/upload', $payload);
        $response->assertOk();

        $attempt = PassimarkAttempt::where('user_id', $user->id)
            ->where('session_state->local_id', $localId)
            ->firstOrFail();

        // Controller must have corrected completed_at so it >= started_at
        $this->assertTrue($attempt->finished_at->gte($attempt->started_at));
    }
}
