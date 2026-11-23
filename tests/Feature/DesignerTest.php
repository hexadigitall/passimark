<?php

namespace Tests\Feature;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignerTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private User $student;
    private PassimarkSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::create([
            'name' => 'Instructor Author',
            'email' => 'instructor@passimark.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->student = User::create([
            'name' => 'Student Learner',
            'email' => 'student@passimark.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $track = PassimarkCertificationTrack::create([
            'title' => 'Designer Testing Track',
            'slug' => 'designer-track',
            'cert_key' => 'designer',
            'is_active' => true,
        ]);

        $this->session = PassimarkSession::create([
            'certification_track_id' => $track->id,
            'number' => 1,
            'phase' => 1,
            'title' => 'Authoring Lab',
            'order' => 1,
            'pass_score' => 70,
            'question_count' => 10,
        ]);
    }

    public function test_designer_access_control(): void
    {
        // Instructor can access designer
        $res = $this->actingAs($this->instructor)->get('/admin/designer');
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page->component('Passimark/Designer'));

        // Student is forbidden
        $studentRes = $this->actingAs($this->student)->get('/admin/designer');
        $studentRes->assertForbidden();
    }

    public function test_author_interactive_drag_drop_question(): void
    {
        $payload = [
            'session_id' => $this->session->id,
            'type' => 'drag_drop',
            'content' => 'Pair OSI protocol tokens to layers.',
            'difficulty' => 0.45,
            'discrimination' => 1.35,
            'guessing' => 0.0,
            'domain' => 'Network Security',
            'bloom_level' => 'Apply',
            'explanation' => 'TCP operates at L4, IP at L3.',
            'reference' => 'RFC 793, RFC 791',
            'interactive_payload' => [
                'sources' => [
                    ['id' => 's1', 'label' => 'TCP'],
                    ['id' => 's2', 'label' => 'IP'],
                ],
                'targets' => [
                    ['id' => 't1', 'slot_label' => 'Transport', 'correct_source_id' => 's1'],
                    ['id' => 't2', 'slot_label' => 'Network', 'correct_source_id' => 's2'],
                ],
            ],
        ];

        $res = $this->actingAs($this->instructor)->postJson('/admin/questions', $payload);
        $res->assertCreated();

        $this->assertDatabaseHas('passimark_questions', [
            'session_id' => $this->session->id,
            'type' => 'drag_drop',
            'content' => 'Pair OSI protocol tokens to layers.',
            'difficulty' => 0.45,
            'discrimination' => 1.35,
        ]);
    }

    public function test_author_hotspot_question_with_psychometrics(): void
    {
        $payload = [
            'session_id' => $this->session->id,
            'type' => 'hotspot',
            'content' => 'Click the firewall appliance in the network topology diagram.',
            'difficulty' => 1.10,
            'discrimination' => 1.50,
            'guessing' => 0.0,
            'domain' => 'Perimeter Defense',
            'bloom_level' => 'Analyze',
            'reference' => 'NIST SP 800-41 Rev 1',
            'interactive_payload' => [
                'image_src' => 'assets/network.png',
                'target_shape' => 'circle',
                'target_bounds' => ['center_x' => 120, 'center_y' => 240, 'radius' => 25],
            ],
        ];

        $res = $this->actingAs($this->instructor)->postJson('/admin/questions', $payload);
        $res->assertCreated();

        $this->assertDatabaseHas('passimark_questions', [
            'session_id' => $this->session->id,
            'type' => 'hotspot',
            'difficulty' => 1.10,
        ]);
    }

    public function test_update_existing_question_in_designer(): void
    {
        $question = PassimarkQuestion::create([
            'session_id' => $this->session->id,
            'type' => 'single_choice',
            'content' => 'Old content',
            'options' => [
                ['key' => 'A', 'text' => 'Option A', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Option B', 'is_correct' => false],
            ],
            'difficulty' => 0.0,
        ]);

        $updatePayload = [
            'content' => 'Updated question content via Designer',
            'difficulty' => 0.85,
            'options' => [
                ['key' => 'A', 'text' => 'Option A Updated', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Option B', 'is_correct' => false],
            ],
        ];

        $res = $this->actingAs($this->instructor)->putJson("/admin/questions/{$question->id}", $updatePayload);
        $res->assertOk();

        $this->assertDatabaseHas('passimark_questions', [
            'id' => $question->id,
            'content' => 'Updated question content via Designer',
            'difficulty' => 0.85,
        ]);
    }
}
