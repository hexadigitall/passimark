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

class AdvancedQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private PassimarkSession $session;
    private PassimarkExam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::create([
            'name' => 'Advanced Candidate',
            'email' => 'advanced@passimark.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $track = PassimarkCertificationTrack::create([
            'title' => 'Advanced Item Types Track',
            'slug' => 'advanced-items',
            'cert_key' => 'advanced',
            'is_active' => true,
        ]);

        $this->session = PassimarkSession::create([
            'certification_track_id' => $track->id,
            'number' => 1,
            'phase' => 1,
            'title' => 'Sprint 11 Advanced Items Lab',
            'order' => 1,
            'pass_score' => 70,
            'question_count' => 10,
        ]);

        $this->exam = PassimarkExam::create([
            'session_id' => $this->session->id,
            'title' => 'Advanced Exam',
            'mode' => 'timed',
            'question_count' => 10,
            'irt_enabled' => false,
        ]);

        PassimarkProgress::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'status' => 'open',
            'ability_theta' => 0.0,
        ]);
    }

    public function test_build_list_flow_and_anti_leakage(): void
    {
        $question = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'build_list',
            'content' => 'Arrange the incident response steps in sequence.',
            'options' => [],
            'interactive_payload' => [
                'available_items' => [
                    ['id' => 'step-1', 'text' => 'Preparation'],
                    ['id' => 'step-2', 'text' => 'Detection & Analysis'],
                    ['id' => 'step-3', 'text' => 'Containment, Eradication & Recovery'],
                ],
                'correct_sequence' => ['step-1', 'step-2', 'step-3'],
            ],
            'explanation' => 'NIST SP 800-61 Rev 2 defines this lifecycle.',
        ]);

        // Second question so session doesn't prematurely terminate
        PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'single_choice',
            'content' => 'Follow up',
            'options' => [
                ['key' => 'A', 'text' => 'A', 'is_correct' => true],
            ],
            'correct_key' => 'A',
        ]);

        $attempt = PassimarkAttempt::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'mode' => 'timed',
            'theta' => 0.0,
            'started_at' => now(),
        ]);

        // Verify sanitization
        $res = $this->actingAs($this->learner)->get("/passimark/attempt/{$attempt->id}");
        $res->assertOk();
        $payload = $res->viewData('page')['props']['question']['interactive_payload'];
        $this->assertArrayNotHasKey('correct_sequence', $payload);

        // Submit answer
        $ans = $this->actingAs($this->learner)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'interaction_response' => ['step-1', 'step-2', 'step-3'],
        ]);
        $ans->assertOk();
        $ans->assertJson(['correct' => true, 'score' => 1.0]);
    }

    public function test_cloze_flow_and_anti_leakage(): void
    {
        $question = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'cloze',
            'content' => 'Complete the cryptographic sentence.',
            'options' => [],
            'interactive_payload' => [
                'template' => 'For asymmetric encryption use {{b1}} and for symmetric use {{b2}}.',
                'blanks' => [
                    'b1' => ['options' => ['RSA', 'AES'], 'correct_answer' => 'RSA'],
                    'b2' => ['options' => ['RSA', 'AES'], 'correct_answer' => 'AES'],
                ],
            ],
        ]);

        PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'single_choice',
            'content' => 'Follow up',
            'options' => [['key' => 'A', 'text' => 'A', 'is_correct' => true]],
            'correct_key' => 'A',
        ]);

        $attempt = PassimarkAttempt::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'mode' => 'timed',
            'theta' => 0.0,
            'started_at' => now(),
        ]);

        $res = $this->actingAs($this->learner)->get("/passimark/attempt/{$attempt->id}");
        $res->assertOk();
        $payload = $res->viewData('page')['props']['question']['interactive_payload'];
        $this->assertArrayNotHasKey('correct_answer', $payload['blanks']['b1']);
        $this->assertArrayNotHasKey('correct_answer', $payload['blanks']['b2']);

        $ans = $this->actingAs($this->learner)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'interaction_response' => [
                'b1' => 'RSA',
                'b2' => 'AES',
            ],
        ]);
        $ans->assertOk();
        $ans->assertJson(['correct' => true, 'score' => 1.0]);
    }

    public function test_create_tree_flow_and_anti_leakage(): void
    {
        $question = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'create_tree',
            'content' => 'Construct the zero trust network hierarchy.',
            'options' => [],
            'interactive_payload' => [
                'root_label' => 'Zero Trust Architecture',
                'available_nodes' => [
                    ['id' => 'pep', 'label' => 'Policy Enforcement Point'],
                    ['id' => 'pdp', 'label' => 'Policy Decision Point'],
                ],
                'correct_edges' => [
                    ['parent' => 'root', 'child' => 'pdp'],
                    ['parent' => 'pdp', 'child' => 'pep'],
                ],
            ],
        ]);

        PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'single_choice',
            'content' => 'Follow up',
            'options' => [['key' => 'A', 'text' => 'A', 'is_correct' => true]],
            'correct_key' => 'A',
        ]);

        $attempt = PassimarkAttempt::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'mode' => 'timed',
            'theta' => 0.0,
            'started_at' => now(),
        ]);

        $res = $this->actingAs($this->learner)->get("/passimark/attempt/{$attempt->id}");
        $res->assertOk();
        $payload = $res->viewData('page')['props']['question']['interactive_payload'];
        $this->assertArrayNotHasKey('correct_edges', $payload);

        $ans = $this->actingAs($this->learner)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'interaction_response' => [
                ['parent' => 'root', 'child' => 'pdp'],
                ['parent' => 'pdp', 'child' => 'pep'],
            ],
        ]);
        $ans->assertOk();
        $ans->assertJson(['correct' => true, 'score' => 1.0]);
    }

    public function test_fill_blank_flow_and_anti_leakage(): void
    {
        $question = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'fill_blank',
            'content' => 'What is the default port for HTTPS?',
            'options' => [],
            'interactive_payload' => [
                'input_type' => 'number',
                'accepted_answers' => ['443'],
            ],
        ]);

        PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'type' => 'single_choice',
            'content' => 'Follow up',
            'options' => [['key' => 'A', 'text' => 'A', 'is_correct' => true]],
            'correct_key' => 'A',
        ]);

        $attempt = PassimarkAttempt::create([
            'user_id' => $this->learner->id,
            'session_id' => $this->session->id,
            'exam_id' => $this->exam->id,
            'mode' => 'timed',
            'theta' => 0.0,
            'started_at' => now(),
        ]);

        $res = $this->actingAs($this->learner)->get("/passimark/attempt/{$attempt->id}");
        $res->assertOk();
        $payload = $res->viewData('page')['props']['question']['interactive_payload'];
        $this->assertArrayNotHasKey('accepted_answers', $payload);

        $ans = $this->actingAs($this->learner)->postJson("/passimark/attempt/{$attempt->id}/answer", [
            'question_id' => $question->id,
            'interaction_response' => ['text' => '443'],
        ]);
        $ans->assertOk();
        $ans->assertJson(['correct' => true, 'score' => 1.0]);
    }
}
