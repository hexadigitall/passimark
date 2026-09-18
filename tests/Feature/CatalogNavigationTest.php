<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PassimarkSeeder;
use Database\Seeders\WorldwidePassimarkCatalogSeeder;
use Tests\TestCase;

class CatalogNavigationTest extends TestCase
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
                        ['name' => 'Threats & Vulnerabilities', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Threat Actors', 'Social Engineering']],
                    ],
                ],
                'CISSP' => [
                    'name' => 'ISC2 CISSP - Gold Standard',
                    'desc' => 'CISSP 8 Domains - 150Q adaptive',
                    'pass_score' => 70, 'final_q' => 150, 'final_time' => 180, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Security & Risk Management', 'phase_q' => 75, 'phase_time' => 90, 'lessons' => ['Governance Frameworks', 'Risk Assessment']],
                    ],
                ],
            ],
            'GLOBAL-CLOUD' => [
                'AWS-CCP' => [
                    'name' => 'AWS Cloud Practitioner CLF-C02',
                    'desc' => 'Cloud fundamentals',
                    'pass_score' => 70, 'final_q' => 50, 'final_time' => 60, 'mock_count' => 1,
                    'phases' => [
                        ['name' => 'Cloud Concepts', 'phase_q' => 30, 'phase_time' => 40, 'lessons' => ['Value Proposition']],
                    ],
                ],
            ],
        ];
    }

    public function test_dashboard_tiles_are_certification_categories_with_aggregates(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $page = $this->actingAs($student)->get('/')->assertOk()->viewData('page');
        $categories = collect($page['props']['sections'])->flatMap(fn ($section) => $section['categories'])->keyBy('cert_key');

        $this->assertSame(['aws-ccp', 'cissp', 'sec'], $categories->keys()->sort()->values()->all());
        $this->assertSame(2, $categories['cissp']['bundle_count']);
        $this->assertSame(1, $categories['aws-ccp']['bundle_count']);
        $this->assertArrayNotHasKey('sessions', $categories['cissp']);
        $this->assertArrayHasKey('sessions_total', $categories['cissp']);

        // A tile can be identified by cert_key and carries the category title.
        $this->assertSame('CompTIA Security+ SY0-701', $categories['sec']['title']);
        $this->assertSame('USA-IT-SECURITY', $categories['sec']['region']);
    }

    public function test_region_filter_narrows_dashboard_categories(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $page = $this->actingAs($student)->get('/?region=GLOBAL-CLOUD')->assertOk()->viewData('page');
        $categories = collect($page['props']['sections'])->flatMap(fn ($section) => $section['categories']);

        $this->assertSame(['aws-ccp'], $categories->pluck('cert_key')->all());
        $this->assertSame(['GLOBAL-CLOUD'], collect($page['props']['sections'])->pluck('region')->all());
    }

    public function test_cert_screen_lists_bundles_for_the_category(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $page = $this->actingAs($student)->get('/certs/cissp')->assertOk()->viewData('page');

        $this->assertSame('cissp', $page['props']['category']['cert_key']);
        $this->assertSame(2, $page['props']['category']['bundle_count']);
        $this->assertSame(['cissp', 'cissp-v2'], collect($page['props']['bundles'])->pluck('slug')->sort()->values()->all());
        $this->assertNotEmpty($page['props']['bundles'][0]['url']);
    }

    public function test_unknown_cert_is_not_found(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)->get('/certs/not-a-cert')->assertNotFound();
    }

    public function test_bundle_screen_returns_session_ladder_and_metadata(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $page = $this->actingAs($student)->get('/certs/cissp/cissp-v2')->assertOk()->viewData('page');

        $track = $page['props']['track'];
        $this->assertSame('cissp-v2', $track['slug']);
        $this->assertSame('cissp', $track['cert_key']);
        $this->assertNotEmpty($track['sessions']);
        $this->assertArrayHasKey('progress', $track['sessions'][0]);
        $this->assertTrue($track['sessions'][0]['assessable']);
        $this->assertFalse($track['sessions'][0]['optional']);
        $this->assertArrayHasKey('theta_history', $track);
        $this->assertArrayHasKey('domains', $track);

        // The sibling bundle is offered as a variant switcher.
        $this->assertContains('cissp', collect($page['props']['siblings'])->pluck('slug')->all());
    }

    public function test_bundle_screen_rejects_a_mismatched_cert(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)->get('/certs/aws-ccp/cissp-v2')->assertNotFound();
        $this->actingAs($student)->get('/certs/cissp/does-not-exist')->assertNotFound();
    }

    public function test_dashboard_continue_hero_present_for_enrolled_learner(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $page = $this->actingAs($student)->get('/')->assertOk()->viewData('page');

        $this->assertArrayHasKey('continueSession', $page['props']);
        $this->assertNotNull($page['props']['continueSession']);
        $this->assertArrayHasKey('url', $page['props']['continueSession']);
        $this->assertArrayHasKey('session_id', $page['props']['continueSession']);
    }
}
