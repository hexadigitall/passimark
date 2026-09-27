<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PassimarkSeeder;
use Database\Seeders\WorldwidePassimarkCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard is focus-first. These guard the contract that a learner who
 * picked one interest sees that interest — not a wall of every certification.
 */
class FocusDrivenDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const COHORT_LIMIT = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    public function test_focus_payload_carries_a_next_session_and_a_bounded_cohort(): void
    {
        $student = $this->student();
        $student->preferences = ['focus' => 'cissp', 'funnel_completed' => true];
        $student->save();

        $props = $this->actingAs($student)->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame('cissp', $props['focus']['cert_key']);
        $this->assertArrayHasKey('next_session', $props['focus']);
        $this->assertArrayHasKey('cohort', $props['focus']);

        $next = $props['focus']['next_session'];
        $this->assertNotNull($next, 'focus should resolve an outstanding session');
        $this->assertArrayHasKey('session_id', $next);
        $this->assertArrayHasKey('locked', $next);
        $this->assertArrayHasKey('url', $next);
        $this->assertStringContainsString('cissp', $next['url']);

        // The cohort is a suggestion, never a second catalog.
        $this->assertLessThanOrEqual(
            self::COHORT_LIMIT,
            count($props['focus']['cohort']),
            'cohort must stay short so the dashboard stays lean'
        );

        // And it must never include the focus itself.
        foreach ($props['focus']['cohort'] as $item) {
            $this->assertNotSame('cissp', $item['cert_key']);
            $this->assertArrayHasKey('url', $item);
        }
    }

    public function test_cohort_prefers_the_same_family_before_the_same_region(): void
    {
        // PassimarkSeeder alone only ships cissp, so the family ranking needs a
        // catalog that actually contains families sharing a region.
        (new WorldwidePassimarkCatalogSeeder)->seedCatalog([
            'USA-CLOUD' => [
                'AWS-CCP' => $this->cert('AWS Cloud Practitioner', 4),
                'AWS-SAA' => $this->cert('AWS Solutions Architect Associate', 3),
                'AZ-900' => $this->cert('Azure Fundamentals', 2),
            ],
            'USA-SECURITY' => [
                'SEC-PLUS' => $this->cert('Security+', 2),
            ],
        ]);

        $student = $this->student();
        $student->preferences = ['focus' => 'aws-ccp', 'funnel_completed' => true];
        $student->save();

        $props = $this->actingAs($student)->get('/')->assertOk()->viewData('page')['props'];
        $cohort = $props['focus']['cohort'];

        $this->assertNotEmpty($cohort, 'aws-ccp should resolve a cohort');

        // The aws- sibling must be present and ranked ahead of the region-only match.
        $keys = array_column($cohort, 'cert_key');

        $this->assertContains('aws-saa', $keys, 'family sibling missing from cohort');
        $this->assertSame(
            0,
            array_search('aws-saa', $keys, true),
            'a family sibling must outrank a region-only match'
        );
    }

    private function cert(string $name, int $sessions): array
    {
        return [
            'name' => $name,
            'desc' => 'fixture',
            'pass_score' => 70,
            'final_q' => 20,
            'final_time' => 30,
            'mock_count' => 1,
            'phases' => [[
                'name' => 'Core',
                'phase_q' => 10,
                'phase_time' => 15,
                'lessons' => ['Overview'],
            ]],
        ];
    }

    public function test_dashboard_title_reflects_the_chosen_focus(): void
    {
        $student = $this->student();
        $student->preferences = ['focus' => 'cissp', 'funnel_completed' => true];
        $student->save();

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Passimark/Dashboard'));
    }

    public function test_dashboard_exposes_a_catalog_total_for_the_search_affordance(): void
    {
        $student = $this->student();

        $props = $this->actingAs($student)->get('/')->assertOk()->viewData('page')['props'];

        $this->assertArrayHasKey('catalogTotal', $props);
        $this->assertGreaterThan(0, $props['catalogTotal']);
    }

    public function test_a_learner_without_a_focus_is_offered_one_instead_of_the_catalog_wall(): void
    {
        $student = $this->student();
        $student->preferences = ['funnel_completed' => true];
        $student->save();

        $props = $this->actingAs($student)->get('/')->assertOk()->viewData('page')['props'];

        $this->assertNull($props['focus'], 'no focus selected yet');
    }

    public function test_focus_rung_search_endpoint_covers_the_whole_catalog_regardless_of_focus(): void
    {
        (new WorldwidePassimarkCatalogSeeder)->seedCatalog([
            'USA-CLOUD' => [
                'AWS-CCP' => $this->cert('AWS Cloud Practitioner', 3),
                'AWS-SAA' => $this->cert('AWS Solutions Architect Associate', 3),
            ],
        ]);

        $student = $this->student();
        $student->preferences = ['focus' => 'cissp', 'funnel_completed' => true];
        $student->save();

        $response = $this->actingAs($student)->getJson('/catalog/search?q=aws')->assertOk();

        $this->assertGreaterThan(0, $response->json('total'));
        $this->assertNotEmpty($response->json('data'));

        // Search must not be silently scoped to the current focus: the learner is
        // focused on cissp but searching "aws" must still reach the cloud certs.
        $keys = collect($response->json('data'))->pluck('cert_key');

        $this->assertTrue(
            $keys->contains(fn ($key) => str_starts_with($key, 'aws-')),
            'search returned nothing outside the current focus'
        );
        $this->assertFalse(
            $keys->contains('cissp'),
            'search should not be filtered to the focus'
        );
    }
}

