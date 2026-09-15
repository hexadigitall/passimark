<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkAttemptAnswer;
use App\Models\PassimarkExam;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use Database\Seeders\PassimarkSeeder;
use Database\Seeders\WorldwidePassimarkCatalogSeeder;
use Tests\TestCase;

class DashboardV4PayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->seed(PassimarkSeeder::class);
        (new WorldwidePassimarkCatalogSeeder)->seedCatalog($this->regionFixture());
    }

    private function regionFixture(): array
    {
        return [
            'USA-IT-SECURITY' => [
                'SEC+' => [
                    'name' => 'CompTIA Security+ SY0-701',
                    'desc' => 'Global entry cybersecurity',
                    'pass_score' => 75, 'final_q' => 90, 'final_time' => 90, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Threats & Vulnerabilities', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Threat Actors', 'Social Engineering', 'Malware Types']],
                    ],
                ],
                'CISSP' => [
                    'name' => 'ISC2 CISSP - Gold Standard',
                    'desc' => 'CISSP 8 Domains - 150Q adaptive',
                    'pass_score' => 70, 'final_q' => 150, 'final_time' => 180, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Security & Risk Management', 'phase_q' => 75, 'phase_time' => 90, 'lessons' => ['Governance Frameworks', 'Risk Assessment', 'Legal Compliance']],
                    ],
                ],
            ],
            'GLOBAL-CLOUD' => [
                'AWS-CCP' => [
                    'name' => 'AWS Cloud Practitioner CLF-C02',
                    'desc' => 'Cloud fundamentals',
                    'pass_score' => 70, 'final_q' => 50, 'final_time' => 60, 'mock_count' => 1,
                    'phases' => [
                        ['name' => 'Cloud Concepts', 'phase_q' => 30, 'phase_time' => 40, 'lessons' => ['Value Proposition', 'Design Principles']],
                    ],
                ],
            ],
        ];
    }

    public function test_dashboard_shares_worldwide_tracks_grouped_by_region(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $page = $this->actingAs($student)->get('/')->assertOk()->viewData('page');

        $this->assertArrayHasKey('tracks', $page['props']);
        $this->assertNotEmpty($page['props']['tracks']);
        $this->assertGreaterThanOrEqual(3, count($page['props']['tracks']));

        $regions = array_values(array_unique(array_column($page['props']['tracks'], 'region')));
        sort($regions);
        $this->assertSame(['GLOBAL-CLOUD', 'USA-IT-SECURITY'], $regions);

        $track = $page['props']['tracks'][0];
        $this->assertArrayHasKey('region', $track);
        $this->assertArrayHasKey('sessions', $track);
        $this->assertArrayHasKey('theta_history', $track);
        $this->assertArrayHasKey('domains', $track);

        // A fresh learner has no trend or heatmap data yet.
        $this->assertSame([], $track['theta_history']);
        $this->assertSame([], $track['domains']);
    }

    public function test_dashboard_region_filter_returns_only_matching_tracks(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $pageSingle = $this->actingAs($student)->get('/?region=GLOBAL-CLOUD')->assertOk()->viewData('page');
        $this->assertNotEmpty($pageSingle['props']['tracks']);
        $this->assertSame(['aws-ccp'], array_column($pageSingle['props']['tracks'], 'slug'));

        $pageAll = $this->actingAs($student)->get('/')->assertOk()->viewData('page');
        $this->assertGreaterThan(count($pageSingle['props']['tracks']), count($pageAll['props']['tracks']));
    }

    public function test_dashboard_reflects_theta_history_and_domain_accuracy_after_final_attempts(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $session = PassimarkSession::where('cert_slug', 'cissp')->where('phase_type', 'lesson')->orderBy('order')->firstOrFail();
        $exam = PassimarkExam::where('session_id', $session->id)->where('mode', 'cat')->firstOrFail();

        $question = PassimarkQuestion::create([
            'session_id' => $session->id,
            'exam_id' => $exam->id,
            'content' => 'Which framework drives senior-management risk reporting?',
            'options' => [
                ['key' => 'a', 'text' => 'ISO 27001', 'is_correct' => true],
                ['key' => 'b', 'text' => 'Sales ledger', 'is_correct' => false],
            ],
            'difficulty' => 0, 'discrimination' => 1.2, 'guessing' => 0.25,
            'domain' => 'Security & Risk Management', 'bloom_level' => 'understand', 'correct_key' => 'a',
        ]);

        PassimarkAttempt::create([
            'user_id' => $student->id,
            'session_id' => $session->id,
            'exam_id' => $exam->id,
            'mode' => 'cat',
            'theta' => 1.3,
            'score' => 77,
            'is_passed' => true,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(2),
        ]);
        $attempt = PassimarkAttempt::where('user_id', $student->id)->latest('id')->firstOrFail();
        PassimarkAttemptAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option' => 'a',
            'is_correct' => true,
            'time_spent' => 6,
        ]);

        $page = $this->actingAs($student)->get('/')->assertOk()->viewData('page');

        $cissp = collect($page['props']['tracks'])->firstWhere('slug', 'cissp');
        $this->assertNotNull($cissp, 'cissp track must be present in payload');
        $this->assertContains(1.3, $cissp['theta_history']);

        $domain = collect($cissp['domains'])->firstWhere('name', 'Security & Risk Management');
        $this->assertNotNull($domain, 'domain missing from heatmap');
        $this->assertSame(1, $domain['total']);
        $this->assertSame(1, $domain['correct']);
        $this->assertSame(1.0, $domain['accuracy']);
    }

    public function test_shell_abilities_share_ability_estimate_and_regions(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $page = $this->actingAs($student)->get('/')->assertOk()->viewData('page');

        $this->assertArrayHasKey('regions', $page['props']);
        $this->assertNotEmpty($page['props']['regions']);
        $this->assertArrayHasKey('ability', $page['props']);
        $this->assertArrayHasKey('theta', $page['props']['ability']);
        $this->assertSame(0.0, $page['props']['ability']['theta']);
    }
}