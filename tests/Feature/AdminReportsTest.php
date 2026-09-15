<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkAttempt;
use App\Models\PassimarkAttemptAnswer;
use App\Models\PassimarkExam;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@passimark.com')->firstOrFail();
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    private function examFor(int $sessionId, string $mode): PassimarkExam
    {
        return PassimarkExam::where('session_id', $sessionId)->where('mode', $mode)->firstOrFail();
    }

    private function makeAttempt(User $student, string $mode, ?int $score, ?bool $passed, bool $finished = true): PassimarkAttempt
    {
        return PassimarkAttempt::create([
            'user_id' => $student->id,
            'session_id' => 1,
            'exam_id' => $this->examFor(1, $mode)->id,
            'mode' => $mode,
            'theta' => 0.5,
            'started_at' => now()->subMinutes(45),
            'finished_at' => $finished ? now() : null,
            'score' => $score,
            'is_passed' => (bool) $passed,
        ]);
    }

    public function test_each_report_route_renders_for_admin(): void
    {
        $expectations = [
            '/admin/reports/learners' => 'Passimark/Reports/Learners',
            '/admin/reports/attempts' => 'Passimark/Reports/Attempts',
            '/admin/reports/sessions' => 'Passimark/Reports/Sessions',
            '/admin/reports/questions' => 'Passimark/Reports/Questions',
            '/admin/reports/tracks' => 'Passimark/Reports/Tracks',
            '/admin/reports/approvals' => 'Passimark/Reports/Approvals',
        ];

        foreach ($expectations as $path => $component) {
            $this->actingAs($this->admin())->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_student_is_forbidden_from_report_routes(): void
    {
        $this->actingAs($this->student())->get('/admin/reports/learners')->assertForbidden();
        $this->actingAs($this->student())->get('/admin/reports/attempts')->assertForbidden();
    }

    public function test_learners_report_lists_students_with_engagement(): void
    {
        $this->makeAttempt($this->student(), 'cat', 80, true);

        $this->actingAs($this->admin())->get('/admin/reports/learners')
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/Reports/Learners')
                    ->where('summary.total', 1)
                    ->where('summary.active', 1)
                    ->has('rows', 1)
                    ->where('rows.0.name', 'TechPoet Dimeji')
                    ->where('rows.0.attempts', 1)
                    ->where('rows.0.avg_score', 80);
            });
    }

    public function test_attempts_report_computes_scores_distribution_modes_and_trend(): void
    {
        $student = $this->student();
        $this->makeAttempt($student, 'cat', 80, true);
        $this->makeAttempt($student, 'practice', 55, false);
        $this->makeAttempt($student, 'timed', null, false, finished: false);

        $this->actingAs($this->admin())->get('/admin/reports/attempts')
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/Reports/Attempts')
                    ->where('summary.total', 3)
                    ->where('summary.finished', 2)
                    ->where('summary.passed', 1)
                    ->where('summary.failed', 1)
                    ->where('summary.in_progress', 1)
                    ->where('summary.avg_score', 67.5)
                    ->where('summary.median_score', 67.5)
                    ->where('summary.highest', 80)
                    ->where('bands.50–69', 1)
                    ->where('bands.70–84', 1)
                    ->where('modes.0.total', 1)
                    ->where('modes.0.mode', 'cat')
                    ->has('trend', 7)
                    ->has('rows', 3)
                    ->where('filters.status', 'all');
            });

        $this->actingAs($this->admin())->get('/admin/reports/attempts?status=passed')
            ->assertInertia(fn (Assert $page) => $page->where('summary.total', 1)->where('summary.finished', 1));
    }

    public function test_sessions_report_flags_contentless_and_counts_attempts(): void
    {
        $this->makeAttempt($this->student(), 'cat', 80, true);
        PassimarkSession::create(['certification_track_id' => 1, 'number' => 99, 'phase' => 4, 'title' => 'Empty CRUD session', 'order' => 999]);

        $this->actingAs($this->admin())->get('/admin/reports/sessions')
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/Reports/Sessions')
                    ->where('summary.sessions', 47)
                    ->where('summary.contentless', 42)
                    ->where('summary.attempts', 1)
                    ->has('rows', 47)
                    ->where('rows.0.contentless', false)
                    ->where('rows.0.unused', false)
                    ->where('rows.46.contentless', true);
            });
    }

    public function test_questions_report_surfaces_usage_accuracy_and_weakest_items(): void
    {
        $total = PassimarkQuestion::count();
        $weakQuestion = PassimarkQuestion::where('session_id', 1)->firstOrFail();
        $other = PassimarkQuestion::where('session_id', 1)->skip(1)->firstOrFail();

        for ($i = 0; $i < 5; $i++) {
            PassimarkAttemptAnswer::create([
                'attempt_id' => $this->makeAttempt($this->student(), 'cat', 50, false)->id,
                'question_id' => $weakQuestion->id,
                'selected_option' => 'A',
                'is_correct' => false,
            ]);
        }
        PassimarkAttemptAnswer::create([
            'attempt_id' => $this->makeAttempt($this->student(), 'practice', 90, true)->id,
            'question_id' => $other->id,
            'selected_option' => 'B',
            'is_correct' => true,
        ]);

        $this->actingAs($this->admin())->get('/admin/reports/questions')
            ->assertInertia(function (Assert $page) use ($total, $weakQuestion) {
                $page->component('Passimark/Reports/Questions')
                    ->where('summary.total', $total)
                    ->where('summary.used', 2)
                    ->where('summary.accuracy', 16.7)
                    ->where('summary.never_used', $total - 2)
                    ->has('domains')
                    ->has('blooms')
                    ->has('difficulty.easy')
                    ->has('difficulty.mid')
                    ->has('difficulty.hard')
                    ->where('weakest.0.id', $weakQuestion->id)
                    ->where('weakest.0.accuracy', 0)
                    ->has('never_used_samples');
            });
    }

    public function test_tracks_report_counts_sessions_completions_and_pending(): void
    {
        $student = $this->student();
        $this->makeAttempt($student, 'cat', 80, true);

        PassimarkProgress::where('user_id', $student->id)->where('session_id', 1)->update(['status' => PassimarkProgress::COMPLETED]);

        $this->actingAs($this->admin())->get('/admin/reports/tracks')
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/Reports/Tracks')
                    ->where('summary.tracks', 1)
                    ->where('summary.sessions', 46)
                    ->where('summary.enrolled', 1)
                    ->where('summary.completions', 1)
                    ->where('rows.0.sessions', 46)
                    ->where('rows.0.enrolled', 1)
                    ->where('rows.0.attempts', 1)
                    ->where('rows.0.completions', 1);
            });
    }

    public function test_approvals_report_has_ledger_trend_lag_and_pending_queue(): void
    {
        $student = $this->student();
        $progress = PassimarkProgress::where('user_id', $student->id)->where('session_id', 1)->firstOrFail();
        $progress->update(['status' => PassimarkProgress::PENDING, 'score' => 82]);
        PassimarkProgress::firstOrCreate(['user_id' => $student->id, 'session_id' => 2], ['status' => PassimarkProgress::PENDING]);

        $this->actingAs($this->admin())->postJson("/admin/passimark/progress/{$progress->id}/approve", ['note' => 'Solid mastery.'])->assertOk();

        $this->actingAs($this->admin())->get('/admin/reports/approvals')
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/Reports/Approvals')
                    ->where('summary.decisions', 1)
                    ->where('summary.approved', 1)
                    ->where('summary.pending', 1)
                    ->where('summary.avg_lag_hours', 0)
                    ->has('trend', 8)
                    ->where('trend.0.week', now()->startOfWeek()->format('M d'))
                    ->where('rows.0.action', 'approved')
                    ->where('rows.0.learner', 'TechPoet Dimeji')
                    ->where('rows.0.lag_hours', 0);
            });

        $this->actingAs($this->admin())->get('/admin/reports/approvals?filter=pending')
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/Reports/Approvals')
                    ->has('pending', 1)
                    ->where('pending.0.session', PassimarkSession::find(2)->title)
                    ->where('pending.0.id', 2)
                    ->has('summary.pending');
            });
    }

    public function test_landing_tiles_carry_rich_metrics_and_link_to_reports(): void
    {
        $this->makeAttempt($this->student(), 'cat', 80, true);
        $this->makeAttempt($this->student(), 'practice', 55, false);

        $this->actingAs($this->admin())->get('/')
            ->assertInertia(function (Assert $page) {
                $page->component('Passimark/AdminDashboard')
                    ->has('tiles', 10)
                    ->where('tiles.0.key', 'learners')
                    ->where('tiles.0.value', 1)
                    ->where('tiles.0.href', '/admin/reports/learners')
                    ->where('tiles.2.value', 2)
                    ->where('tiles.2.href', '/admin/reports/attempts?status=completed')
                    ->where('tiles.3.key', 'average_score')
                    ->where('tiles.3.value', 67.5)
                    ->where('tiles.3.sub', 'median 67.5 · best 80')
                    ->where('report.average_score', 67.5)
                    ->has('report.sessions');
            });
    }
}