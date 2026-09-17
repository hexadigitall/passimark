<?php
namespace App\Services;

use App\Models\{PassimarkAttempt, PassimarkAttemptAnswer, PassimarkApprovalEvent, PassimarkCertificationTrack, PassimarkProgress, PassimarkQuestion, PassimarkSession, User};
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the admin KPI tiles and drill-down reports.
 * Every method is safe for empty data (zero-division guarded) and deterministic
 * (explicit ordering/limits) so the UI never renders "NaN/%" or unstable rows.
 */
class AdminAnalytics
{
    /** Percent helper - floats or null safe. */
    private function pct(float|int|null $part, float|int|null $total, int $decimals = 0): int|float
    {
        if ($total <= 0) return 0;
        return round(((float) $part / (float) $total) * 100, $decimals);
    }

    /**
     * Broken attemptable sessions: sessions that carry exams but have no questions.
     * Reference/remediation lessons (no exam, no questions) are intentionally contentless
     * (the curriculum ladder skips them) and must NOT be flagged as broken.
     */
    public static function brokenSessionCount(): int
    {
        return PassimarkSession::whereHas('exams')->doesntHave('questions')->count();
    }

    /** Average of an id-to-value map (nullable values skipped). */
    private function avgOf(array $values, ?float $floor = null): ?float
    {
        $nums = array_values(array_filter(array_map(fn ($v) => is_numeric($v) ? (float) $v : null, $values), fn ($v) => $v !== null));
        if (empty($nums)) return null;
        $avg = array_sum($nums) / count($nums);
        return $floor !== null ? max($avg, $floor) : $avg;
    }

    private function medianOf(array $nums): ?float
    {
        if (empty($nums)) return null;
        sort($nums);
        $mid = intdiv(count($nums), 2);
        return count($nums) % 2 ? $nums[$mid] : ($nums[$mid - 1] + $nums[$mid]) / 2;
    }

    /** Bounded set of finished attempt scores for distribution/median stats. */
    private function recentScores(object $baseQuery): array
    {
        return $baseQuery
            ->whereNotNull('finished_at')
            ->whereNotNull('score')
            ->orderByDesc('finished_at')
            ->limit(2000)
            ->pluck('score')
            ->map(fn ($s) => (float) round($s, 2))
            ->all();
    }

    /**
     * Landing KPI tiles. Each tile: key, label, value, sub, href, deltaText.
     * Values double as the flat report keys used by the legacy payload.
     */
    public function tiles(): array
    {
        $now = now();
        $weekAgo = $now->copy()->subDays(7);
        $twoWeeksAgo = $now->copy()->subDays(14);

        $students = User::where('role', 'student')->count();

        $attemptCounts = PassimarkAttempt::toBase()
            ->selectRaw("count(*) as total, sum(case when finished_at is not null then 1 else 0 end) as finished, sum(case when finished_at is not null and is_passed = 1 then 1 else 0 end) as passed")
            ->first();
        $attempts = (int) ($attemptCounts->total ?? 0);
        $finished = (int) ($attemptCounts->finished ?? 0);
        $passed = (int) ($attemptCounts->passed ?? 0);

        $scores = PassimarkAttempt::query()->whereNotNull('finished_at')->whereNotNull('score')->orderByDesc('finished_at')->limit(2000)->pluck('score')->map(fn ($s) => (float) round($s, 2))->all();
        $avgScore = $this->avgOf($scores);
        $medianScore = $this->medianOf($scores);
        $highestScore = empty($scores) ? null : max($scores);

        $approved7d = PassimarkApprovalEvent::where('action', 'approved')->where('created_at', '>=', $weekAgo)->count();
        $approvedPrior7d = PassimarkApprovalEvent::where('action', 'approved')->whereBetween('created_at', [$twoWeeksAgo, $weekAgo])->count();
        $approvedAll = PassimarkApprovalEvent::where('action', 'approved')->count();
        $rejectedAll = PassimarkApprovalEvent::where('action', 'rejected')->count();

        $completions = PassimarkProgress::whereIn('status', [PassimarkProgress::COMPLETED, PassimarkProgress::APPROVED])->count();
        $completionsPrior7d = PassimarkProgress::whereIn('status', [PassimarkProgress::COMPLETED, PassimarkProgress::APPROVED])->whereBetween('updated_at', [$twoWeeksAgo, $weekAgo])->count();
        $pendingCount = PassimarkProgress::where('status', PassimarkProgress::PENDING)->count();
        $pendingPrior7d = PassimarkProgress::where('status', PassimarkProgress::PENDING)->whereBetween('updated_at', [$twoWeeksAgo, $weekAgo])->count();
        $pendingOldest = PassimarkProgress::where('status', PassimarkProgress::PENDING)->orderBy('updated_at')->value('updated_at');
        $pendingOldestDays = $pendingOldest ? max(0, round(now()->diffInDays($pendingOldest), 1)) : 0;

        $sessionsTotal = PassimarkSession::count();
        $questionsTotal = PassimarkQuestion::count();
        $contentless = self::brokenSessionCount();

        $untagged = PassimarkQuestion::doesntHave('tags')->count();
        $missingExplanation = PassimarkQuestion::where(function ($q) {
            $q->whereNull('explanation')->orWhere('explanation', '');
        })->count();

        $tracksTotal = PassimarkCertificationTrack::count();
        $regions = PassimarkCertificationTrack::query()->whereNotNull('region')->distinct()->count('region');
        $certsTotal = (int) (DB::table('passimark_certification_tracks')
            ->select(DB::raw('count(distinct coalesce(cert_key, slug)) as c'))
            ->value('c') ?? 0);

        $activeLearners = PassimarkAttempt::query()->whereNotNull('finished_at')->distinct('user_id')->count('user_id');
        $activeLearnersPrior7d = PassimarkAttempt::query()->whereNotNull('finished_at')->whereBetween('finished_at', [$twoWeeksAgo, $weekAgo])->distinct('user_id')->count('user_id');
        $activeLearners7d = PassimarkAttempt::query()->whereNotNull('finished_at')->where('finished_at', '>=', $weekAgo)->distinct('user_id')->count('user_id');
        $studentsPending = PassimarkProgress::query()->where('status', PassimarkProgress::PENDING)->distinct('user_id')->count('user_id');

        $attemptsPrior7d = PassimarkAttempt::whereBetween('started_at', [$twoWeeksAgo, $weekAgo])->count();
        $attempts7d = PassimarkAttempt::where('started_at', '>=', $weekAgo)->count();
        $completedPrior7d = PassimarkAttempt::whereNotNull('finished_at')->whereBetween('finished_at', [$twoWeeksAgo, $weekAgo])->count();
        $completed7d = PassimarkAttempt::whereNotNull('finished_at')->where('finished_at', '>=', $weekAgo)->count();

        $tiles = [
            [
                'key' => 'learners', 'label' => 'Learners', 'value' => $students, 'href' => '/admin/reports/learners',
                'sub' => "active {$activeLearners} · pending {$studentsPending}",
                'deltaText' => $this->deltaText($activeLearners7d, $activeLearnersPrior7d, 'active this week'),
            ],
            [
                'key' => 'attempts', 'label' => 'Attempts', 'value' => $attempts, 'href' => '/admin/reports/attempts',
                'sub' => "passed {$passed} · failed " . max(0, $finished - $passed),
                'deltaText' => $this->deltaText($attempts7d, $attemptsPrior7d, 'this week'),
            ],
            [
                'key' => 'completed', 'label' => 'Completed', 'value' => $finished, 'href' => '/admin/reports/attempts?status=completed',
                'sub' => "completion rate {$this->pct($finished, $attempts)}%",
                'deltaText' => $this->deltaText($completed7d, $completedPrior7d, 'this week'),
            ],
            [
                'key' => 'average_score', 'label' => 'Average score', 'value' => $avgScore ?? 0, 'href' => '/admin/reports/attempts',
                'sub' => 'median ' . ($medianScore ?? '—') . ' · best ' . ($highestScore ?? '—'),
                'deltaText' => null,
            ],
            [
                'key' => 'approvals_7d', 'label' => 'Approvals (7d)', 'value' => $approved7d, 'href' => '/admin/reports/approvals',
                'sub' => "all-time {$approvedAll} · rejected {$rejectedAll}",
                'deltaText' => $this->deltaText($approved7d, $approvedPrior7d, 'vs prior week'),
            ],
            [
                'key' => 'completions', 'label' => 'Completions', 'value' => $completions, 'href' => '/admin/reports/tracks',
                'sub' => "pending {$pendingCount}" . ($tracksTotal ? " · across {$tracksTotal} track(s)" : ''),
                'deltaText' => $this->deltaText($completions, $completionsPrior7d, 'this week'),
            ],
            [
                'key' => 'sessions', 'label' => 'Sessions', 'value' => $sessionsTotal, 'href' => '/admin/reports/sessions',
                'sub' => "questions {$questionsTotal}" . ($contentless ? " · contentless {$contentless}" : ''),
                'deltaText' => null,
            ],
            [
                'key' => 'questions', 'label' => 'Questions', 'value' => $questionsTotal, 'href' => '/admin/reports/questions',
                'sub' => (
                    ($untagged ? "untagged {$untagged}" : '') .
                    ($untagged && $missingExplanation ? ' · ' : '') .
                    ($missingExplanation ? "no-explanation {$missingExplanation}" : '')
                ),
                'deltaText' => null,
            ],
            [
                'key' => 'tracks', 'label' => 'Tracks', 'value' => $tracksTotal, 'href' => '/admin/reports/tracks',
                'sub' => "regions {$regions} · sessions {$sessionsTotal}",
                'deltaText' => null,
            ],
            [
                'key' => 'certs', 'label' => 'Certifications', 'value' => $certsTotal, 'href' => '/admin/reports/tracks',
                'sub' => "bundles {$tracksTotal} · regions {$regions}",
                'deltaText' => null,
            ],
            [
                'key' => 'pending', 'label' => 'Pending', 'value' => $pendingCount, 'href' => '/admin/reports/approvals?filter=pending',
                'sub' => $pendingOldestDays > 0 ? "oldest {$pendingOldestDays}d awaiting review" : 'nothing to review',
                'deltaText' => $this->deltaText($pendingCount, $pendingPrior7d, 'vs week ago'),
            ],
        ];

        return $tiles;
    }

    /** Human-readable movement vs a prior period; null when there is no baseline/change worth showing. */
    private function deltaText(int $current, int $prior, string $unit): ?string
    {
        $diff = $current - $prior;
        if ($diff === 0) return $prior > 0 ? "unchanged · {$current} {$unit}" : null;
        $arrow = $diff > 0 ? '▲' : '▼';
        return "{$arrow} " . abs($diff) . " · {$current} {$unit}";
    }

    /** Learner roster with per-learner engagement and progression. */
    public function learners(): array
    {
        $students = User::where('role', 'student')->get(['id', 'name', 'email', 'created_at']);
        $ids = $students->pluck('id');

        $attempts = PassimarkAttempt::whereIn('user_id', $ids)->get(['user_id', 'score', 'is_passed', 'started_at', 'finished_at', 'theta']);
        $progress = PassimarkProgress::with('session:id,number,title')
            ->whereIn('user_id', $ids)
            ->where('status', '!=', PassimarkProgress::LOCKED)
            ->get(['user_id', 'session_id', 'status', 'score', 'updated_at']);

        $attemptsByUser = $attempts->groupBy('user_id');
        $progressByUser = $progress->groupBy('user_id');
        $sessionsById = PassimarkSession::query()->whereKey($progress->pluck('session_id')->unique())->pluck('title', 'id');

        $rows = $students->map(function (User $s) use ($attemptsByUser, $progressByUser, $sessionsById) {
            $a = $attemptsByUser->get($s->id, collect());
            $p = $progressByUser->get($s->id, collect());
            $finished = $a->whereNotNull('finished_at');
            $passed = $finished->where('is_passed', true);
            $scores = $finished->pluck('score')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);

            $last = collect([
                $a->max('started_at'),
                $a->max('finished_at'),
                $p->max('updated_at'),
                $s->created_at,
            ])->filter()->max();

            $activeProgress = $p->sortByDesc('updated_at')->first();

            return [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'sessions_total' => $p->count(),
                'attempts' => $a->count(),
                'finished' => $finished->count(),
                'passed' => $passed->count(),
                'pending' => $p->where('status', PassimarkProgress::PENDING)->count(),
                'completed' => $p->whereIn('status', [PassimarkProgress::COMPLETED, PassimarkProgress::APPROVED])->count(),
                'in_progress' => $p->where('status', PassimarkProgress::IN_PROGRESS)->count(),
                'avg_score' => $scores->avg() !== null ? round($scores->avg(), 1) : null,
                'pass_rate' => $this->pct($passed->count(), $finished->count()),
                'theta' => $a->max('theta'),
                'last_activity' => $last,
                'current' => $activeProgress ? $sessionsById[$activeProgress->session_id] ?? null : null,
                'current_status' => $activeProgress?->status,
            ];
        })
            ->sortByDesc('last_activity')
            ->values()
            ->take(100)
            ->values()
            ->all();

        $total = $students->count();
        $active = $attemptsByUser->count();
        $weeklyActive = PassimarkAttempt::whereNotNull('finished_at')->where('finished_at', '>=', now()->subDays(7))->distinct('user_id')->count('user_id');
        $withPending = $progressByUser->filter(fn ($g) => $g->contains('status', PassimarkProgress::PENDING))->count();

        return [
            'summary' => [
                'total' => $total,
                'active' => $active,
                'weekly_active' => $weeklyActive,
                'with_pending' => $withPending,
            ],
            'rows' => $rows,
        ];
    }

    /** Attempt ledger + score distribution + mode/trend analytics. Filters: status, mode. */
    public function attempts(string $status = 'all', string $mode = 'all'): array
    {
        $status = in_array($status, ['all', 'completed', 'passed', 'failed', 'in_progress'], true) ? $status : 'all';
        $mode = in_array($mode, ['all', 'cat', 'practice', 'timed'], true) ? $mode : 'all';

        $base = PassimarkAttempt::query();
        if ($mode !== 'all') $base->where('mode', $mode);
        match ($status) {
            'completed' => $base->whereNotNull('finished_at'),
            'passed' => $base->whereNotNull('finished_at')->where('is_passed', true),
            'failed' => $base->whereNotNull('finished_at')->where('is_passed', false),
            'in_progress' => $base->whereNull('finished_at'),
            default => null,
        };

        $total = (clone $base)->count();
        $finished = (clone $base)->whereNotNull('finished_at')->count();
        $passed = (clone $base)->whereNotNull('finished_at')->where('is_passed', true)->count();

        $scores = $this->recentScores(clone $base);
        $avg = $this->avgOf($scores);
        $median = $this->medianOf($scores);
        $highest = empty($scores) ? null : max($scores);
        $lowest = empty($scores) ? null : min($scores);

        $bands = ['<50' => 0, '50–69' => 0, '70–84' => 0, '85+' => 0];
        foreach ($scores as $s) {
            if ($s < 50) $bands['<50']++;
            elseif ($s < 70) $bands['50–69']++;
            elseif ($s < 85) $bands['70–84']++;
            else $bands['85+']++;
        }

        $modes = [];
        foreach (['cat', 'practice', 'timed'] as $m) {
            $q = (clone $base)->where('mode', $m);
            $mTotal = (clone $q)->count();
            $mFinished = (clone $q)->whereNotNull('finished_at');
            $mPassed = (clone $mFinished)->where('is_passed', true)->count();
            $mScores = (clone $mFinished)->whereNotNull('score')->orderByDesc('finished_at')->limit(2000)->pluck('score')->map(fn ($s) => (float) $s);
            $modes[] = [
                'mode' => $m,
                'total' => $mTotal,
                'finished' => (clone $mFinished)->count(),
                'passed' => $mPassed,
                'avg_score' => $mScores->avg() !== null ? round($mScores->avg(), 1) : null,
                'pass_rate' => $this->pct($mPassed, (clone $mFinished)->count()),
            ];
        }

        $trendStart = now()->subDays(6)->startOfDay();
        $trend = [];
        for ($i = 0; $i < 7; $i++) {
            $start = $trendStart->copy()->addDays($i);
            $dayAgg = (clone $base)->whereNotNull('finished_at')
                ->where('finished_at', '>=', $start)
                ->where('finished_at', '<', $start->copy()->addDay())
                ->selectRaw('count(*) as c, avg(score) as s')
                ->first();
            $trend[] = [
                'day' => $start->format('D'),
                'finished' => (int) ($dayAgg->c ?? 0),
                'avg' => $dayAgg && $dayAgg->s !== null ? round((float) $dayAgg->s, 1) : null,
            ];
        }

        $rows = $base->with(['user:id,name,email', 'session:id,number,title', 'exam:id,title,mode'])
            ->withCount('answers')
            ->orderByDesc('started_at')
            ->limit(200)
            ->get()
            ->map(function (PassimarkAttempt $a) {
                $duration = $a->started_at && $a->finished_at ? max(0, round($a->started_at->diffInSeconds($a->finished_at) / 60, 1)) : null;
                return [
                    'id' => $a->id,
                    'learner' => $a->user?->name,
                    'session' => $a->session?->title,
                    'exam' => $a->exam?->title,
                    'mode' => $a->mode,
                    'score' => $a->score !== null ? round((float) $a->score, 1) : null,
                    'passed' => (bool) $a->is_passed,
                    'theta' => round((float) $a->theta, 2),
                    'questions' => $a->answers_count,
                    'duration' => $duration,
                    'started_at' => $a->started_at,
                    'finished_at' => $a->finished_at,
                ];
            })
            ->all();

        return [
            'summary' => [
                'total' => $total,
                'finished' => $finished,
                'passed' => $passed,
                'failed' => max(0, $finished - $passed),
                'in_progress' => $total - $finished,
                'completion_rate' => $this->pct($finished, $total),
                'avg_score' => $avg !== null ? round($avg, 1) : null,
                'median_score' => $median !== null ? round($median, 1) : null,
                'highest' => $highest !== null ? round($highest, 1) : null,
                'lowest' => $lowest !== null ? round($lowest, 1) : null,
            ],
            'bands' => $bands,
            'modes' => $modes,
            'trend' => $trend,
            'rows' => $rows,
            'filters' => ['status' => $status, 'mode' => $mode],
        ];
    }

    /** Per-session engagement analytics + content health flags. */
    public function sessions(): array
    {
        $sessions = PassimarkSession::with('certificationTrack:id,title')
            ->withCount(['questions' => fn ($q) => $q->select(DB::raw('count(*)')), 'exams' => fn ($q) => $q->select(DB::raw('count(*)'))])
            ->orderBy('order')
            ->get();

        $agg = PassimarkAttempt::query()
            ->selectRaw('session_id, count(*) as total, sum(case when finished_at is not null then 1 else 0 end) as finished, sum(case when finished_at is not null and is_passed = 1 then 1 else 0 end) as passed, avg(case when finished_at is not null and score is not null then score end) as avg_score, max(finished_at) as last_finished')
            ->groupBy('session_id')
            ->get()
            ->keyBy('session_id');

        $pending = PassimarkProgress::where('status', PassimarkProgress::PENDING)
            ->selectRaw('session_id, count(*) as c')
            ->groupBy('session_id')
            ->pluck('c', 'session_id');

        $rows = $sessions->map(function (PassimarkSession $s) use ($agg, $pending) {
            $a = $agg->get($s->id);
            $total = (int) ($a->total ?? 0);
            $finished = (int) ($a->finished ?? 0);
            $passed = (int) ($a->passed ?? 0);
            return [
                'id' => $s->id,
                'number' => $s->number,
                'title' => $s->title,
                'track' => $s->certificationTrack?->title,
                'phase_type' => $s->phase_type,
                'order' => $s->order,
                'questions' => $s->questions_count,
                'attempts' => $total,
                'finished' => $finished,
                'avg_score' => $a && $a->avg_score !== null ? round((float) $a->avg_score, 1) : null,
                'pass_rate' => $this->pct($passed, $finished),
                'pending' => (int) ($pending[$s->id] ?? 0),
                'contentless' => $s->exams_count > 0 && $s->questions_count === 0,
                'unused' => $total === 0,
                'last_activity' => $a?->last_finished,
            ];
        });

        $totals = $rows->collect();

        return [
            'summary' => [
                'sessions' => $sessions->count(),
                'contentless' => $totals->where('contentless', true)->count(),
                'unused' => $totals->where('unused', true)->count(),
                'attempts' => (int) collect($agg->values()->pluck('total'))->sum(),
                'questions' => PassimarkQuestion::count(),
                'avg_pass_rate' => $this->pct(collect($agg->values()->pluck('passed'))->sum(), collect($agg->values()->pluck('finished'))->sum()),
            ],
            'rows' => $rows->all(),
        ];
    }

    /** Question/psicometric analytics: domain & bloom, difficulty bands, flags, weakest items. */
    public function questions(): array
    {
        $total = PassimarkQuestion::count();

        $usage = PassimarkAttemptAnswer::query()
            ->selectRaw('question_id, count(*) as used, sum(case when is_correct = 1 then 1 else 0 end) as correct')
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');
        $usedCount = $usage->count();
        $usedTotal = (int) $usage->sum('used');
        $correctTotal = (int) $usage->sum('correct');

        $domainRows = PassimarkQuestion::query()
            ->leftJoin('passimark_attempt_answers', 'passimark_attempt_answers.question_id', '=', 'passimark_questions.id')
            ->selectRaw('passimark_questions.domain, count(distinct passimark_questions.id) as questions, count(passimark_attempt_answers.id) as used, coalesce(sum(passimark_attempt_answers.is_correct), 0) as correct')
            ->groupBy('passimark_questions.domain')
            ->orderByDesc('questions')
            ->get()
            ->map(fn ($r) => [
                'domain' => $r->domain ?: '—',
                'questions' => (int) $r->questions,
                'used' => (int) $r->used,
                'accuracy' => $this->pct($r->correct, $r->used, 1),
            ])
            ->all();

        $bloomRows = PassimarkQuestion::query()
            ->leftJoin('passimark_attempt_answers', 'passimark_attempt_answers.question_id', '=', 'passimark_questions.id')
            ->selectRaw('coalesce(passimark_questions.bloom_level, \'none\') as bloom, count(distinct passimark_questions.id) as questions, count(passimark_attempt_answers.id) as used, coalesce(sum(passimark_attempt_answers.is_correct), 0) as correct')
            ->groupBy('bloom')
            ->orderByDesc('questions')
            ->get()
            ->map(fn ($r) => ['bloom' => $r->bloom, 'questions' => (int) $r->questions, 'used' => (int) $r->used, 'accuracy' => $this->pct($r->correct, $r->used, 1)])
            ->all();

        $difficultyBands = ['easy' => 0, 'mid' => 0, 'hard' => 0];
        foreach (PassimarkQuestion::query()->pluck('difficulty') as $d) {
            if ($d < 0.33) $difficultyBands['easy']++;
            elseif ($d <= 0.67) $difficultyBands['mid']++;
            else $difficultyBands['hard']++;
        }

        $untagged = PassimarkQuestion::doesntHave('tags')->count();
        $missingExplanation = PassimarkQuestion::where(fn ($q) => $q->whereNull('explanation')->orWhere('explanation', ''))->count();
        $lowDiscrimination = PassimarkQuestion::where('discrimination', '<', 0.7)->count();
        $neverUsed = $total - $usedCount;

        $neverUsedSamples = PassimarkQuestion::query()
            ->whereNotIn('id', $usage->keys())
            ->orderBy('id')
            ->limit(5)
            ->get(['id', 'content', 'domain'])
            ->map(fn ($q) => ['id' => $q->id, 'snippet' => mb_strimwidth($q->content, 0, 90, '…'), 'domain' => $q->domain]);
        $weakest = PassimarkQuestion::query()
            ->join('passimark_attempt_answers', 'passimark_attempt_answers.question_id', '=', 'passimark_questions.id')
            ->selectRaw('passimark_questions.id, passimark_questions.content, passimark_questions.domain, count(passimark_attempt_answers.id) as used, round(avg(passimark_attempt_answers.is_correct) * 100, 1) as accuracy')
            ->where('passimark_attempt_answers.question_id', '!=', null)
            ->having('used', '>=', 5)
            ->groupBy('passimark_questions.id', 'passimark_questions.content', 'passimark_questions.domain')
            ->orderBy('accuracy', 'asc')
            ->limit(10)
            ->get()
            ->map(fn ($q) => ['id' => $q->id, 'snippet' => mb_strimwidth($q->content, 0, 90, '…'), 'domain' => $q->domain, 'used' => (int) $q->used, 'accuracy' => (float) $q->accuracy]);

        return [
            'summary' => [
                'total' => $total,
                'used' => $usedCount,
                'accuracy' => $this->pct($correctTotal, $usedTotal, 1),
                'never_used' => $neverUsed,
                'untagged' => $untagged,
                'missing_explanation' => $missingExplanation,
                'low_discrimination' => $lowDiscrimination,
            ],
            'domains' => $domainRows,
            'blooms' => $bloomRows,
            'difficulty' => $difficultyBands,
            'weakest' => $weakest,
            'never_used_samples' => $neverUsedSamples,
        ];
    }

    /** Per-track/certification overview incl. completions matrix. */
    public function tracks(): array
    {
        $tracks = PassimarkCertificationTrack::withCount('sessions')->orderBy('title')->get();

        $sessionIdsByTrack = $tracks->mapWithKeys(fn ($t) => [$t->id => $t->sessions->pluck('id')]);
        $allSessionIds = $sessionIdsByTrack->flatten()->unique();

        $questionsBySession = PassimarkQuestion::whereIn('session_id', $allSessionIds)->selectRaw('session_id, count(*) as c')->groupBy('session_id')->pluck('c', 'session_id');
        $progressByTrack = PassimarkProgress::with('session:id,certification_track_id')->whereIn('session_id', $allSessionIds)->get(['session_id', 'user_id', 'status']);
        $attemptByTrack = PassimarkAttempt::whereIn('session_id', $allSessionIds)->selectRaw('session_id, count(*) as total, sum(case when finished_at is not null then 1 else 0 end) as finished, sum(case when finished_at is not null and is_passed = 1 then 1 else 0 end) as passed, avg(case when finished_at is not null and score is not null then score end) as avg_score')->groupBy('session_id')->get()->keyBy('session_id');

        [$progressGrouped, $attemptGrouped] = [collect(), collect()];
        foreach ($sessionIdsByTrack as $trackId => $ids) {
            $progressGrouped[$trackId] = $progressByTrack->whereIn('session_id', $ids->all());
            $attemptGrouped[$trackId] = $attemptByTrack->whereIn('session_id', $ids->all());
        }

        $rows = $tracks->map(function (PassimarkCertificationTrack $t) use ($sessionIdsByTrack, $questionsBySession, $progressGrouped, $attemptGrouped) {
            $ids = $sessionIdsByTrack[$t->id];
            $progress = $progressGrouped[$t->id];
            $attempts = $attemptGrouped[$t->id];
            $finished = (int) $attempts->sum('finished');
            $passed = (int) $attempts->sum('passed');
            $weightedScore = $attempts->sum(fn ($a) => $a->avg_score !== null ? $a->avg_score * $a->finished : 0);

            return [
                'id' => $t->id,
                'slug' => $t->slug,
                'cert_key' => $t->certKey(),
                'variant_label' => $t->variant_label,
                'title' => $t->title,
                'region' => $t->region,
                'advancement' => $t->advancement,
                'sessions' => $t->sessions_count,
                'questions' => (int) $ids->map(fn ($sid) => (int) ($questionsBySession[$sid] ?? 0))->sum(),
                'enrolled' => $progress->pluck('user_id')->unique()->count(),
                'attempts' => (int) $attempts->sum('total'),
                'avg_score' => $finished ? round($weightedScore / max(1, $finished), 1) : null,
                'completions' => $progress->whereIn('status', [PassimarkProgress::COMPLETED, PassimarkProgress::APPROVED])->count(),
                'pending' => $progress->where('status', PassimarkProgress::PENDING)->count(),
                'pass_rate' => $this->pct($passed, $finished),
            ];
        });

        $allProgress = PassimarkProgress::whereIn('session_id', $allSessionIds);

        return [
            'summary' => [
                'tracks' => $tracks->count(),
                'certs' => $tracks->map(fn ($t) => $t->certKey())->unique()->count(),
                'sessions' => $allSessionIds->count(),
                'questions' => (int) $allSessionIds->map(fn ($sid) => (int) ($questionsBySession[$sid] ?? 0))->sum(),
                'enrolled' => (clone $allProgress)->distinct('user_id')->count('user_id'),
                'completions' => (clone $allProgress)->whereIn('status', [PassimarkProgress::COMPLETED, PassimarkProgress::APPROVED])->count(),
                'pending' => (clone $allProgress)->where('status', PassimarkProgress::PENDING)->count(),
            ],
            'categories' => $tracks->groupBy(fn ($t) => $t->certKey())->map(function ($group, $certKey) {
                return [
                    'cert_key' => $certKey,
                    'label' => $group->first()->title,
                    'variants' => $group->count(),
                    'tracks' => $group->map(fn ($t) => [
                        'id' => $t->id,
                        'slug' => $t->slug,
                        'variant_label' => $t->variant_label,
                        'region' => $t->region,
                        'advancement' => $t->advancement,
                        'sessions' => $t->sessions_count,
                        'is_active' => $t->is_active,
                    ])->values(),
                ];
            })->values(),
            'rows' => $rows->all(),
        ];
    }

    /** Approvals ledger + weekly trend + review lag + pending queue. Filter: all|approved|rejected|pending. */
    public function approvals(string $filter = 'all'): array
    {
        $filter = in_array($filter, ['all', 'approved', 'rejected', 'pending'], true) ? $filter : 'all';

        $events = PassimarkApprovalEvent::with(['progress.user:id,name,email', 'progress.session:id,number,title', 'reviewer:id,name'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $pending = PassimarkProgress::with(['user:id,name,email', 'session:id,number,title'])
            ->where('status', PassimarkProgress::PENDING)
            ->orderBy('updated_at')
            ->get()
            ->map(function (PassimarkProgress $p) {
                $age = $p->updated_at ? max(0, round(now()->diffInHours($p->updated_at) / 24, 1)) : null;
                return [
                    'id' => $p->id,
                    'learner' => $p->user?->name,
                    'email' => $p->user?->email,
                    'session' => $p->session?->title,
                    'score' => $p->score,
                    'requested_at' => $p->updated_at,
                    'age_days' => $age,
                ];
            });

        $lag = now();
        foreach ($events as $e) {
            if ($e->progress && $e->progress->updated_at && $e->progress->user) {
                $e->_lagHours = max(0, round($e->created_at->diffInHours($e->progress->updated_at), 1));
            }
        }
        $avgLag = $this->avgOf($events->filter(fn ($e) => isset($e->_lagHours))->pluck('_lagHours')->all());

        $decided = $events->whereIn('action', ['approved', 'rejected']);
        $filtered = match ($filter) {
            'approved' => $decided->where('action', 'approved'),
            'rejected' => $decided->where('action', 'rejected'),
            'pending' => collect(),
            default => $decided,
        };

        $trend = [];
        $eightWeeks = PassimarkApprovalEvent::where('created_at', '>=', now()->subWeeks(8)->startOfWeek())
            ->get(['id', 'created_at', 'action']);
        for ($i = 0; $i < 8; $i++) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = $start->copy()->addWeek();
            $inWeek = $eightWeeks->filter(fn ($e) => $e->created_at >= $start && $e->created_at < $end);
            $trend[] = [
                'week' => $start->format('M d'),
                'approved' => $inWeek->where('action', 'approved')->count(),
                'rejected' => $inWeek->where('action', 'rejected')->count(),
            ];
        }

        $rows = $filtered->map(function (PassimarkApprovalEvent $e) {
            return [
                'id' => $e->id,
                'action' => $e->action,
                'learner' => $e->progress?->user?->name ?? '—',
                'session' => $e->progress?->session?->title ?? '—',
                'reviewer' => $e->reviewer?->name ?? '—',
                'note' => $e->note,
                'lag_hours' => $e->_lagHours ?? null,
                'decided_at' => $e->created_at,
            ];
        });

        return [
            'summary' => [
                'decisions' => $decided->count(),
                'approved' => $decided->where('action', 'approved')->count(),
                'rejected' => $decided->where('action', 'rejected')->count(),
                'avg_lag_hours' => $avgLag !== null ? round($avgLag, 1) : null,
                'pending' => $pending->count(),
            ],
            'trend' => $trend,
            'rows' => $rows->all(),
            'pending' => $pending->all(),
            'activeFilter' => $filter,
        ];
    }
}