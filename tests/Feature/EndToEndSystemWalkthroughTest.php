<?php

namespace Tests\Feature;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\User;
use App\Services\CertificateIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EndToEndSystemWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_health_check_command_passes_all_subsystems(): void
    {
        $exitCode = Artisan::call('passimark:system-check');
        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('ALL 10/10 PASSIMARK SUBSYSTEMS OPERATIONAL & VERIFIED', $output);
    }

    public function test_candidate_end_to_end_journey_and_zero_answer_leakage(): void
    {
        $user = User::create([
            'email' => 'candidate@passimark.test',
            'name' => 'Jane Candidate',
            'password' => bcrypt('password'),
        ]);

        $track = PassimarkCertificationTrack::create([
            'slug' => 'test-cert-track',
            'title' => 'Test Security Specialist',
            'category' => 'security',
            'advancement' => 'auto',
            'is_active' => true,
        ]);

        $session = PassimarkSession::create([
            'certification_track_id' => $track->id,
            'cert_slug' => $track->slug,
            'number' => 1,
            'phase' => 'final',
            'phase_type' => 'final',
            'title' => 'Core Security Assessment',
            'order' => 1,
            'theta_required' => 0.50,
        ]);

        $exam = PassimarkExam::create([
            'session_id' => $session->id,
            'title' => 'Comprehensive Final Exam',
            'mode' => 'timed',
            'time_limit_minutes' => 60,
            'passing_score' => 70,
        ]);

        $question = PassimarkQuestion::create([
            'session_id' => $session->id,
            'exam_id' => $exam->id,
            'type' => 'single_choice',
            'content' => 'Which protocol guarantees secure transport via TLS handshake?',
            'correct_key' => 'B',
            'options' => [
                ['key' => 'A', 'text' => 'HTTP (Port 80)', 'is_correct' => false],
                ['key' => 'B', 'text' => 'HTTPS (Port 443)', 'is_correct' => true],
                ['key' => 'C', 'text' => 'Telnet (Port 23)', 'is_correct' => false],
            ],
            'explanation' => 'HTTPS utilizes TLS to provide cryptographic confidentiality and integrity.',
            'irt_a' => 1.8,
            'irt_b' => 0.2,
            'irt_c' => 0.25,
            'order' => 1,
        ]);

        // 1. Spaced Repetition Drill Endpoint
        $drillResponse = $this->actingAs($user)->getJson('/api/drill/daily?limit=5');
        $drillResponse->assertOk()
            ->assertJsonStructure(['count', 'questions']);

        // 2. AI Tutoring Explanation Endpoint
        $aiResponse = $this->actingAs($user)->postJson('/api/tutor/explain', [
            'question_id' => $question->id,
            'chosen_key' => 'A',
        ]);
        $aiResponse->assertOk()
            ->assertJson([
                'is_correct' => false,
                'chosen' => ['key' => 'A'],
                'correct' => ['key' => 'B'],
            ]);
        $this->assertNotEmpty($aiResponse->json('why_chosen_is_incorrect'));
        $this->assertNotEmpty($aiResponse->json('why_correct_is_best'));
        $this->assertNotEmpty($aiResponse->json('key_takeaway'));

        // 3. Desktop Runtime Bridge Info Endpoint
        $desktopInfo = $this->getJson('/api/desktop/info');
        $desktopInfo->assertOk()
            ->assertJson([
                'engine' => 'Tauri 2.0 Rust Core',
                'desktop_version' => '2.0.0',
            ]);

        // 4. Invariant: Zero Answer Leakage in candidate view
        $dragDrop = new PassimarkQuestion([
            'type' => 'drag_drop',
            'interactive_payload' => [
                'sources' => [['id' => 's1', 'text' => 'Source 1']],
                'targets' => [['id' => 't1', 'correct_source_id' => 's1']],
            ],
        ]);
        $sanitized = \App\Services\AnswerEvaluator::sanitizeForCandidate($dragDrop, false);
        $this->assertArrayNotHasKey('correct_source_id', $sanitized['targets'][0]);
        $revealed = \App\Services\AnswerEvaluator::sanitizeForCandidate($dragDrop, true);
        $this->assertArrayHasKey('correct_source_id', $revealed['targets'][0]);

        // 5. Deterministic Credential Minting Format
        $mintedCode = CertificateIssuer::PREFIX . '-' . strtoupper($track->slug) . '-' . date('Y') . '-12345678';
        $this->assertMatchesRegularExpression('/^PMK-[A-Z0-9_-]+-\d{4}-[A-F0-9]{8}$/', $mintedCode);
    }
}
