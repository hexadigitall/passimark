<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkAttemptAnswer;
use App\Models\PassimarkCertificationTrack;
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

    public function test_dashboard_shares_certification_categories_grouped_by_region(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $page = $this->actingAs($student)->get('/')->assertOk()->viewData('page');

        $this->assertArrayHasKey('sections', $page['props']);
        $this->assertNotEmpty($page['props']['sections']);

        $sections = collect($page['props']['sections']);
        $this->assertSame(['GLOBAL-CLOUD', 'General', 'USA-IT-SECURITY'], $sections->pluck('region')->sort()->values()->all());

        $categories = $sections->flatMap(fn ($section) => $section['categories'])->keyBy('cert_key');
        $this->assertSame(3, $categories->count());

        // Two bundles share the cissp category (legacy PassimarkSeeder + worldwide fork).
        $this->assertSame(2, $categories['cissp']['bundle_count']);
        $this->assertSame(1, $categories['aws-ccp']['bundle_count']);

        // Tiles carry aggregates, never session arrays.
        foreach ($categories as $category) {
            $this->assertArrayHasKey('sessions_total', $category);
            $this->assertArrayHasKey('percent', $category);
            $this->assertArrayNotHasKey('sessions', $category);
        }

        $this->assertArrayHasKey('stats', $page['props']);
        $this->assertSame(3, $page['props']['stats']['categories']);
    }

    public function test_dashboard_region_filter_returns_only_matching_categories(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $pageSingle = $this->actingAs($student)->get('/?region=GLOBAL-CLOUD')->assertOk()->viewData('page');
        $singleCategories = collect($pageSingle['props']['sections'])->flatMap(fn ($section) => $section['categories']);
        $this->assertSame(['aws-ccp'], $singleCategories->pluck('cert_key')->all());

        $pageAll = $this->actingAs($student)->get('/')->assertOk()->viewData('page');
        $allCategories = collect($pageAll['props']['sections'])->flatMap(fn ($section) => $section['categories']);
        $this->assertGreaterThan($singleCategories->count(), $allCategories->count());
    }

    public function test_track_screen_carries_theta_history_and_domain_accuracy_after_final_attempts(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $track = PassimarkCertificationTrack::where('slug', 'cissp-v2')->firstOrFail();
        $session = $track->sessions()->where('phase_type', 'lesson')->orderBy('order')->firstOrFail();
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

        $page = $this->actingAs($student)->get('/certs/cissp/cissp-v2')->assertOk()->viewData('page');

        $this->assertSame('cissp-v2', $page['props']['track']['slug']);
        $this->assertSame('cissp', $page['props']['track']['cert_key']);
        $this->assertContains(1.3, $page['props']['track']['theta_history']);

        $domain = collect($page['props']['track']['domains'])->firstWhere('name', 'Security & Risk Management');
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
