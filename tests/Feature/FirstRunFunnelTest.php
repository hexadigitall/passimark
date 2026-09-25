<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FirstRunFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    public function test_guest_at_root_lands_on_the_first_run_lock_rung(): void
    {
        $this->get('/')
            ->assertRedirect(route('passimark.funnel.lock'));
    }

    public function test_guest_can_walk_the_pre_login_ladder(): void
    {
        $rungs = [
            'passimark.funnel.lock' => 'Passimark/Funnel/Lock',
            'passimark.funnel.splash' => 'Passimark/Funnel/Splash',
            'passimark.funnel.intro' => 'Passimark/Funnel/Intro',
            'passimark.funnel.auth' => 'Passimark/Funnel/Auth',
        ];

        foreach ($rungs as $name => $component) {
            $this->get(route($name))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('funnel.ladder', ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'])
                );
        }
    }

    public function test_pre_login_ladder_chains_forward_and_the_auth_rung_hands_off_to_login(): void
    {
        $this->get(route('passimark.funnel.lock'))
            ->assertInertia(fn (Assert $page) => $page->where('funnel.next', route('passimark.funnel.splash')));

        $this->get(route('passimark.funnel.splash'))
            ->assertInertia(fn (Assert $page) => $page->where('funnel.next', route('passimark.funnel.intro')));

        $this->get(route('passimark.funnel.intro'))
            ->assertInertia(fn (Assert $page) => $page->where('funnel.next', route('passimark.funnel.auth')));

        $this->get(route('passimark.funnel.auth'))
            ->assertInertia(fn (Assert $page) => $page->where('funnel.next', route('login')));
    }

    public function test_gated_rungs_still_require_authentication(): void
    {
        $this->get(route('passimark.funnel.focus'))->assertRedirect(route('login'));
        $this->get(route('passimark.funnel.permissions'))->assertRedirect(route('login'));
    }

    public function test_authenticated_learner_keeps_the_dashboard_at_root(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Passimark/Dashboard'));
    }

    public function test_authenticated_learner_can_walk_the_gated_rungs(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)->get(route('passimark.funnel.focus'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Funnel/Focus')
                ->where('funnel.next', route('passimark.funnel.permissions'))
            );

        $this->actingAs($student)->get(route('passimark.funnel.permissions'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Funnel/Permissions')
                ->where('funnel.next', route('dashboard'))            );
    }

    public function test_signing_in_continues_the_ladder_at_the_focus_rung(): void
    {
        $this->post(route('login'), [
            'email' => 'student@passimark.com',
            'password' => 'password',
        ])->assertRedirect(route('passimark.funnel.focus'));
    }

    public function test_completing_the_ladder_sends_returning_learners_straight_to_the_dashboard(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $student->preferences = ['focus' => 'aws-cp', 'funnel_completed' => true];
        $student->save();

        $this->post(route('login'), [
            'email' => 'student@passimark.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_staff_never_enter_the_learner_funnel(): void
    {
        $this->post(route('login'), [
            'email' => 'admin@passimark.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_focus_rung_offers_the_real_catalog_grouped_by_region(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $response = $this->actingAs($student)->get(route('passimark.funnel.focus'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertNotEmpty($props['options']);
        $this->assertSame('cissp', $props['options'][0]['cert_key']);
        $this->assertArrayHasKey('region', $props['options'][0]);
    }

    public function test_saving_a_focus_persists_it_and_advances_to_permissions(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)
            ->post(route('passimark.funnel.focus.update'), ['cert_key' => 'cissp'])
            ->assertRedirect(route('passimark.funnel.permissions'));

        $this->assertSame('cissp', $student->fresh()->preferences['focus']);
    }

    public function test_saving_a_focus_rejects_a_certification_that_does_not_exist(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)
            ->from(route('passimark.funnel.focus'))
            ->post(route('passimark.funnel.focus.update'), ['cert_key' => 'not-a-real-cert'])
            ->assertRedirect(route('passimark.funnel.focus'))
            ->assertSessionHasErrors('cert_key');

        $this->assertArrayNotHasKey('focus', $student->fresh()->preferences ?? []);
    }

    public function test_completing_permissions_sets_the_flag_and_lands_on_the_dashboard(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)
            ->post(route('passimark.funnel.permissions.complete'))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($student->fresh()->preferences['funnel_completed']);
    }

    public function test_dashboard_reads_the_saved_focus_back(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $student->preferences = ['focus' => 'cissp', 'funnel_completed' => true];
        $student->save();

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/Dashboard')
                ->where('focus.cert_key', 'cissp')
                ->where('focus.title', 'CISSP')
                ->has('focus.sessions_total')
                ->has('focus.percent')
            );
    }

    public function test_dashboard_focus_is_null_when_nothing_was_chosen(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $student->preferences = ['funnel_completed' => true];
        $student->save();

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('focus', null));
    }
}
