<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    public function test_admin_lands_on_the_operations_dashboard_instead_of_the_learner_path(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/AdminDashboard')
                    ->where('report.learners', 1)
                    ->where('report.pending_approvals', 0)
                    ->where('report.completions', 0)
                    ->has('report.sessions')
                    ->has('report.questions')
                    ->has('report.tracks')
                    ->has('report.approved_7d')
                    ->where('pending', [])
                    ->has('needsAttention.contentless_sessions')
                    ->has('needsAttention.empty_tracks')
                    ->has('needsAttention.untagged_questions')
                    ->has('events');
            });
    }

    public function test_instructor_also_lands_on_the_operations_dashboard(): void
    {
        $instructor = User::create([
            'name' => 'Ops Lead',
            'email' => 'instructor@passimark.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $this->actingAs($instructor)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Passimark/AdminDashboard'));
    }

    public function test_student_keeps_the_learner_dashboard(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $this->actingAs($student)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Passimark/Dashboard'));
    }

    public function test_admin_dashboard_reflects_a_pending_approval_in_report_and_queue(): void
    {
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $progress = \App\Models\PassimarkProgress::where('user_id', $student->id)->where('session_id', 1)->firstOrFail();
        $progress->update(['status' => 'pending_approval', 'score' => 84]);

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/AdminDashboard')
                    ->where('report.pending_approvals', 1)
                    ->has('pending', 1);
            });
    }
}