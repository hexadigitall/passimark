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

class InteractiveQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private PassimarkSession $session;
    private PassimarkExam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::create([
            'name' => 'Test Candidate',
            'email' => 'candidate@passimark.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $track = PassimarkCertificationTrack::create([
            'title' => 'Test Interactive Track',
            'slug' => 'test-interactive',
            'cert_key' => 'interactive',
            'is_active' => true,
        ]);

        $this->session = PassimarkSession::create([
            'certification_track_id' => $track->id,
            'number' => 1,
            'phase' => 1,
            'title' => 'Interactive Items Lab',
            'order' => 1,
            'pass_score' => 70,
            'question_count' => 5,
        ]);

        $this->exam = PassimarkExam::create([
            'session_id' => $this->session->id,
            'title' => 'Interactive Exam',
            'mode' => 'practice',
            'question_count' => 5,
            'irt_enabled' => false,
        ]);

        PassimarkProgress::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'status' => 'open',
            'ability_theta' => 0.0,
        ]);
    }

    public function test_interactive_drag_drop_flow_and_payload_sanitization(): void
    {
        $ddQuestion = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'drag_drop',
            'content' => 'Match OSI layers to protocols',
            'options' => [],
            'interactive_payload' => [
                'sources' => [
                    ['id' => 's-tcp', 'label' => 'TCP'],
                    ['id' => 's-ip', 'label' => 'IP'],
                ],
                'targets' => [
                    ['id' => 't-trans', 'slot_label' => 'Transport', 'correct_source_id' => 's-tcp'],
                    ['id' => 't-net', 'slot_label' => 'Network', 'correct_source_id' => 's-ip'],
                ],
            ],
            'explanation' => 'TCP is Layer 4; IP is Layer 3.',
        ]);

        // Second question so that the attempt does not terminate immediately
        PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'single_choice',
            'content' => 'Follow up question',
            'options' => [
                ['key' => 'A', 'text' => 'Option A', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Option B', 'is_correct' => false],
            ],
            'correct_key' => 'A',
        ]);

        $attempt = PassimarkAttempt::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'mode' => 'timed', // timed mode must NEVER leak answer keys
            'theta' => 0.0,
            'started_at' => now(),
        ]);

        // Verify exam endpoint sanitizes candidate payload
        $response = $this->actingAs($this->learner)->get("/passimark/attempt/{$attempt->id}");
        $response->assertOk();

        $page = $response->viewData('page');
        $presentedQuestion = $page['props']['question'];
        $this->assertEquals('drag_drop', $presentedQuestion['type']);

        // Assert correct_source_id is NOT in the candidate payload
        $targets = $presentedQuestion['interactive_payload']['targets'];
        $this->assertArrayNotHasKey('correct_source_id', $targets[0]);
        $this->assertArrayNotHasKey('correct_source_id', $targets[1]);

        // Submit answer
        $answerResponse = $this->actingAs($this->learner)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $ddQuestion->id,
            'interaction_response' => [
                't-trans' => 's-tcp',
                't-net' => 's-ip',
            ],
            'time_spent' => 12,
        ]);

        $answerResponse->assertOk();
        $answerResponse->assertJson([
            'correct' => true,
            'score' => 1.0,
        ]);

        $this->assertDatabaseHas('passimark_attempt_answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $ddQuestion->id,
            'is_correct' => true,
        ]);
    }

    public function test_hotspot_submission_and_scoring(): void
    {
        $hotspotQuestion = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'hotspot',
            'content' => 'Click on the core router',
            'options' => [],
            'interactive_payload' => [
                'image_src' => '/images/topology.png',
                'target_shape' => 'circle',
                'target_bounds' => [
                    'center_x' => 200,
                    'center_y' => 150,
                    'radius' => 30,
                ],
            ],
            'explanation' => 'The core router connects the distribution switches.',
        ]);

        // Second question so that the attempt does not terminate immediately
        PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'single_choice',
            'content' => 'Follow up question 2',
            'options' => [
                ['key' => 'A', 'text' => 'Option A', 'is_correct' => true],
            ],
            'correct_key' => 'A',
        ]);

        $attempt = PassimarkAttempt::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'mode' => 'practice',
            'theta' => 0.0,
            'started_at' => now(),
        ]);

        // Submit hit within target radius
        $hitResponse = $this->actingAs($this->learner)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $hotspotQuestion->id,
            'interaction_response' => ['x' => 210, 'y' => 160],
            'time_spent' => 8,
        ]);

        $hitResponse->assertOk();
        $hitResponse->assertJson([
            'correct' => true,
            'score' => 1.0,
            'explanation' => 'The core router connects the distribution switches.',
        ]);
    }
}
