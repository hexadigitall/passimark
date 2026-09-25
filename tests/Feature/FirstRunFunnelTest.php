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
}
