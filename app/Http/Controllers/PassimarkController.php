<?php
namespace App\Http\Controllers;
use App\Models\{PassimarkSession, PassimarkExam, PassimarkQuestion, PassimarkAttempt, PassimarkProgress};
use App\Services\CatEngine;
use App\Services\AnswerEvaluator;
use App\Services\Irt\Irt3PL;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PassimarkController extends Controller
{
    public function dashboard(Request $request){
        // A guest lands on the first-run ladder (rung 1, Lock) instead of a login wall.
        if (! Auth::check()) {
            return redirect()->route('passimark.funnel.lock');
        }

        // Staff land on the operations dashboard; learners get the catalog tile drill-down.
        if (in_array(Auth::user()?->role, ['admin', 'instructor'], true)) {
            return app(PassimarkAdminController::class)->dashboard();
        }

        return app(PassimarkCatalogController::class)->index($request);
    }

    public function profile()
    {
        $records = PassimarkProgress::with('session.certificationTrack')->where('user_id', Auth::id())->get();
        $scored = $records->filter(fn ($record) => $record->score !== null);
        $active = $records
            ->filter(fn ($record) => in_array($record->status, ['open', 'in_progress'], true) && $record->session)
            ->sortBy(fn ($record) => $record->session->order);
        $current = $active->first();

        $summary = [
            'current_phase' => $current?->session?->phase,
            'current_session' => $current?->session?->number,
            'current_title' => $current?->session?->title,
            'current_track' => $current?->session?->certificationTrack?->title,
            'next_milestone' => $current?->session?->title,
            'sessions_completed' => $records->whereIn('status', ['completed', 'pending_approval', 'approved'])->count(),
            'sessions_enrolled' => $records->count(),
            'sessions_total' => PassimarkSession::where('question_count', '>', 0)->count(),
            'average_score' => $scored->isEmpty() ? null : round($scored->avg('score'), 2),
            'ability_theta' => round((float) ($records->max('ability_theta') ?? 0), 4),
            'pending_approvals' => $records->where('status', 'pending_approval')->count(),
            'tracks' => \App\Models\PassimarkCertificationTrack::where('is_active', true)->count(),
        ];

        $certificates = $records
            ->filter(fn ($record) => $record->certified_at !== null && $record->session)
            ->sortByDesc('certified_at')
            ->map(fn ($record) => \App\Services\CertificateIssuer::summary($record))
            ->values();

        return Inertia::render('Passimark/Profile', [
            'user' => Auth::user(),
            'summary' => $summary,
            'certificates' => $certificates,
        ]);
    }

    public function settings()
    {
        return Inertia::render('Passimark/Settings', [
            'user' => Auth::user(),
            'preferences' => $this->preferences(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'notifications' => ['nullable', 'array'],
            'notifications.session_reminders' => ['boolean'],
            'notifications.approval_updates' => ['boolean'],
            'notifications.assessment_deadlines' => ['boolean'],
            'notifications.weekly_digest' => ['boolean'],
            'theme' => ['nullable', Rule::in(['dark', 'light', 'system'])],
            'language' => ['nullable', 'string', 'max:8'],
        ]);

        $user = $request->user();
        $user->preferences = array_replace($this->preferences(), $data);
        $user->save();

        return back()->with('success', 'Preferences saved.');
    }

    /**
     * Learner preferences with defaults, tolerant of pre-migration / legacy rows.
     */
    private function preferences(): array
    {
        $defaults = [
            'notifications' => [
                'session_reminders' => true,
                'approval_updates' => true,
                'assessment_deadlines' => false,
                'weekly_digest' => true,
            ],
            'theme' => 'dark',
            'language' => 'en',
        ];
        $stored = Auth::user()->preferences ?? [];
        return array_replace_recursive($defaults, is_array($stored) ? $stored : []);
    }

    public function sessionsApi(Request $request)
    {
        $progress = PassimarkProgress::where('user_id', Auth::id())->get()->keyBy('session_id');
        $sessions = PassimarkSession::query()
            ->when($request->filled('phase'), fn ($query) => $query->where('phase', $request->integer('phase')))
            ->orderBy('number')
            ->get()
            ->map(function ($session) use ($progress) {
                $item = $progress->get($session->id);
                return [
                    'id' => $session->id,
                    'number' => $session->number,
                    'title' => $session->title,
                    'domain' => $session->domain,
                    'question_count' => $session->question_count,
                    'pass_score' => $session->pass_score,
                    'progress' => [
                        'status' => $item?->status ?? 'locked',
                        'score' => $item?->score,
                        'attempts' => $item?->attempts ?? 0,
                    ],
                ];
            })
            ->when($request->filled('status'), fn ($collection) => $collection->where('progress.status', $request->string('status')->toString())->values());

        return response()->json(['data' => $sessions->values()]);
    }

    public function progressApi()
    {
        $records = PassimarkProgress::where('user_id', Auth::id())->get();
        $scored = $records->filter(fn ($record) => $record->score !== null);

        return response()->json(['data' => [
            'sessions_attempted' => $records->where('attempts', '>', 0)->count(),
            'sessions_completed' => $records->whereIn('status', ['completed', 'pending_approval', 'approved'])->count(),
            'average_score' => $scored->isEmpty() ? null : round($scored->avg('score'), 2),
            'current_phase' => optional($records->sortByDesc('session_id')->first()?->session)->phase ?? 1,
            'ability_theta' => $records->max('ability_theta') ?? 0,
        ]]);
    }

    public function exam(PassimarkAttempt $attempt)
    {
        abort_unless($attempt->user_id === Auth::id() && !$attempt->finished_at, 404);

        $attempt->load('session', 'exam');
        $isCat = $attempt->mode === 'cat';

        $existingAnswers = $attempt->answers()
            ->get()
            ->keyBy('question_id')
            ->map(fn ($ans) => [
                'question_id' => $ans->question_id,
                'selected_option' => $ans->selected_option,
                'interaction_response' => $ans->interaction_response,
                'is_correct' => $attempt->mode === 'practice' ? (bool)$ans->is_correct : null,
                'time_spent' => $ans->time_spent,
            ]);

        $questions = null;
        $currentQuestion = null;

        if (!$isCat) {
            // For Timed and Practice: pre-load all exam questions (sanitized) so navigation is instantaneous
            $limit = $attempt->exam->question_count ?? 25;
            $rawQuestions = PassimarkQuestion::where('session_id', $attempt->session_id)
                ->orderBy('id')
                ->take($limit)
                ->get();

            $questions = $rawQuestions->map(fn ($q) => $this->presentQuestion($q, $attempt->mode))->values()->all();

            $answeredIds = $existingAnswers->keys()->all();
            $firstUnanswered = $rawQuestions->firstWhere(fn ($q) => !in_array($q->id, $answeredIds, true));
            $currentQuestion = $this->presentQuestion($firstUnanswered ?? $rawQuestions->first(), $attempt->mode);
        } else {
            $currentQuestion = $this->presentQuestion(CatEngine::nextQuestion($attempt), $attempt->mode);
        }

        return Inertia::render('Passimark/Exam', [
            'attempt' => $attempt,
            'question' => $currentQuestion,
            'questions' => $questions,
            'existingAnswers' => $existingAnswers,
            'answeredCount' => $existingAnswers->count(),
            'isPaused' => $attempt->paused_at !== null,
            'remainingSeconds' => $attempt->remaining_seconds,
            'sessionState' => $attempt->session_state ?? [],
        ]);
    }

    public function result(PassimarkAttempt $attempt)
    {
        abort_unless($attempt->user_id === Auth::id() && $attempt->finished_at, 404);

        return Inertia::render('Passimark/Result', \App\Services\ReviewService::payload($attempt, Auth::id()));
    }

    public function review(PassimarkSession $session)
    {
        $attempt = PassimarkAttempt::where('user_id', Auth::id())
            ->where('session_id', $session->id)
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->first();
        abort_unless($attempt, 404, 'No completed attempt to review for this session.');

        return Inertia::render('Passimark/Result', \App\Services\ReviewService::payload($attempt, Auth::id()));
    }

    /**
     * Client-safe question shape. Answer keys stay hidden unless the mode gives per-question
     * feedback (practice), so a reattempt never exposes the correct answer ahead of time.
     */
    private function presentQuestion(?PassimarkQuestion $question, string $mode): ?array
    {
        if (!$question) {
            return null;
        }
        $reveal = $mode === 'practice';
        $options = collect($question->options ?? []);
        $correctKeys = $options->filter(fn ($o) => !empty($o['is_correct']))->pluck('key')->map(fn ($k) => (string) $k)->values()->all();
        $multi = count($correctKeys) > 1 || ($question->type === 'multiple_choice');
        $type = $question->type ?? ($multi ? 'multiple_choice' : 'single_choice');

        $payload = [
            'id' => $question->id,
            'type' => $type,
            'content' => $question->content,
            'domain' => $question->domain,
            'b_difficulty' => $question->difficulty,
            'multi_select' => $multi,
            'correct_keys' => $multi ? $correctKeys : [],
            'options' => $options->map(fn ($option) => array_merge(
                ['key' => $option['key'], 'text' => $option['text']],
                $reveal ? ['is_correct' => (bool) ($option['is_correct'] ?? false)] : []
            ))->values(),
            'interactive_payload' => AnswerEvaluator::sanitizeForCandidate($question, $reveal),
            'exhibits' => $question->exhibits ?? [],
        ];

        if ($reveal && !$multi) {
            $payload['correct_key'] = $question->correct_key ?? ($options->firstWhere('is_correct', true)['key'] ?? null);
        }

        return $payload;
    }

    public function start(Request $r, PassimarkSession $session){
        $prog = PassimarkProgress::where('user_id',Auth::id())->where('session_id',$session->id)->firstOrFail();
        abort_unless(in_array($prog->status,['open','approved','in_progress','completed']),403,'Session locked. Awaiting instructor approval.');
        $mode = $r->validate(['mode' => ['nullable', Rule::in(['cat', 'timed', 'practice'])]])['mode'] ?? 'cat';
        // An exam can only ever serve the items attached to its own session, so the
        // advertised length is capped by that pool. Authored bundle sessions hold
        // 19 items each; advertising the 150-item CAT default made a finished
        // session read as "Question 19 of 150" with a 12% progress bar.
        $pool = (int) PassimarkQuestion::where('session_id', $session->id)->count();
        $defaultCount = $mode==='cat'?150:25;
        $exam = $session->exams()->firstOrCreate(
            ['mode'=>$mode],
            [
                'title'=>"{$session->title} - ".strtoupper($mode),
                'question_count'=>$pool>0?min($defaultCount,$pool):$defaultCount,
                // Every CAT exam runs the v4 IRT engine - that is the platform
                // invariant the catalog seeders hold. A CAT exam created without
                // it fell back to the legacy path, which then scored the attempt
                // with item parameters that authored bundles do not carry.
                'irt_enabled'=>$mode==='cat',
            ]
        );
        $existing = PassimarkAttempt::where('user_id', Auth::id())
            ->where('session_id', $session->id)
            ->where('mode', $mode)
            ->whereNull('finished_at')
            ->latest('id')
            ->first();
        if ($existing) {
            return redirect()->route('passimark.exam', ['attempt' => $existing]);
        }
        $attempt = PassimarkAttempt::create(['user_id'=>Auth::id(),'session_id'=>$session->id,'exam_id'=>$exam->id,'mode'=>$mode,'theta'=>$prog->ability_theta,'started_at'=>now()]);
        $prog->update(['status'=>'in_progress']);
        $next = CatEngine::nextQuestion($attempt);
        return redirect()->route('passimark.exam', ['attempt' => $attempt]);
    }
    public function answer(Request $r, PassimarkAttempt $attempt){
        abort_unless($attempt->user_id === Auth::id() && !$attempt->finished_at, 404);
        $r->validate([
            'question_id' => 'required|integer',
            'selected' => 'nullable|string',
            'selections' => 'nullable|array',
            'selections.*' => 'string',
            'interaction_response' => 'nullable',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $q = PassimarkQuestion::findOrFail($r->question_id);
        abort_unless($q->session_id === $attempt->session_id, 422, 'Question does not belong to this assessment.');
        if ($attempt->mode === 'cat') {
            abort_unless($q->id === CatEngine::nextQuestion($attempt)?->id, 422, 'Question is not the current assessment question.');
        }

        $type = $q->type ?? 'single_choice';
        $submission = [
            'selected' => $r->selected,
            'selections' => $r->selections,
            'interaction_response' => $r->interaction_response,
        ];

        $interactiveTypes = ['drag_drop', 'hotspot', 'build_list', 'fill_blank', 'cloze', 'create_tree', 'case_study', 'simulation'];

        if (!in_array($type, $interactiveTypes, true)) {
            $selected = $r->selected ?? implode(',', collect($r->selections ?? [])->filter()->map(fn($v)=>trim((string)$v))->filter()->sort()->values()->all());
            abort_unless(is_string($selected) && $selected !== '', 422, 'Please select at least one answer.');
            $options = collect($q->options ?? []);
            $allKeys = $options->pluck('key')->map(fn($k)=>(string)$k);
            foreach (preg_split('/[,\s]+/', $selected) as $k) {
                $k = trim((string)$k);
                if ($k === '') continue;
                abort_unless($allKeys->contains($k), 422, 'Selected option is invalid.');
            }
        } else {
            abort_unless(
                !empty($r->interaction_response) || !empty($r->selected) || !empty($r->selections),
                422,
                'Please provide an answer.'
            );
        }

        $evalResult = AnswerEvaluator::evaluate($q, $submission);
        $isCorrect = $evalResult['is_correct'];

        $existingAns = $attempt->answers()->where('question_id', $q->id)->first();
        $totalTime = ($existingAns?->time_spent ?? 0) + (int)($r->time_spent ?? 0);

        $attempt->answers()->updateOrCreate(
            ['question_id' => $q->id],
            [
                'selected_option' => $evalResult['selected_summary'],
                'interaction_response' => $evalResult['raw_response'],
                'is_correct' => $isCorrect,
                'time_spent' => $totalTime,
            ]
        );

        // Spaced repetition Leitner progression tracking
        try {
            app(\App\Services\SpacedRepetition\LeitnerEngine::class)->recordAttempt($attempt->user_id, $q->id, $isCorrect);
        } catch (\Throwable $e) {
            // Non-blocking for exam continuity
        }

        if ($attempt->mode === 'cat') {
            if (CatEngine::usesIrt($attempt)) {
                $attempt->update(['theta' => Irt3PL::mle(CatEngine::answeredParams($attempt), $attempt->theta)['theta']]);
            } else {
                $attempt->updateTheta($isCorrect, $q->difficulty, $q->discrimination, $q->guessing);
            }
            $attempt->refresh();
            $next = CatEngine::nextQuestion($attempt);
            if (CatEngine::shouldTerminate($attempt) || !$next) {
                return $this->finish($attempt);
            }
        } else {
            $next = CatEngine::nextQuestion($attempt);
            if (!$next) {
                return $this->finish($attempt);
            }
        }

        return response()->json([
            'correct' => $isCorrect,
            'score' => $evalResult['score'],
            'feedback' => $evalResult['feedback'] ?? null,
            'explanation' => $attempt->mode === 'practice' ? $q->explanation : null,
            'next' => $this->presentQuestion($next, $attempt->mode),
            'theta' => $attempt->theta,
            'answeredCount' => $attempt->answers()->count(),
            'question_id' => $q->id,
        ]);
    }

    public function pause(Request $request, PassimarkAttempt $attempt)
    {
        abort_unless($attempt->user_id === Auth::id() && !$attempt->finished_at, 404);
        $data = $request->validate([
            'remaining_seconds' => 'nullable|integer|min:0',
            'session_state' => 'nullable|array',
        ]);

        $attempt->update([
            'paused_at' => now(),
            'remaining_seconds' => $data['remaining_seconds'] ?? $attempt->remaining_seconds,
            'session_state' => array_merge($attempt->session_state ?? [], $data['session_state'] ?? []),
        ]);

        return response()->json([
            'status' => 'paused',
            'remaining_seconds' => $attempt->remaining_seconds,
        ]);
    }

    public function resume(Request $request, PassimarkAttempt $attempt)
    {
        abort_unless($attempt->user_id === Auth::id() && !$attempt->finished_at, 404);
        $attempt->update(['paused_at' => null]);

        return response()->json([
            'status' => 'resumed',
            'remaining_seconds' => $attempt->remaining_seconds,
            'session_state' => $attempt->session_state ?? [],
        ]);
    }

    public function saveState(Request $request, PassimarkAttempt $attempt)
    {
        abort_unless($attempt->user_id === Auth::id() && !$attempt->finished_at, 404);
        $data = $request->validate([
            'remaining_seconds' => 'nullable|integer|min:0',
            'session_state' => 'nullable|array',
        ]);

        $attempt->update([
            'remaining_seconds' => $data['remaining_seconds'] ?? $attempt->remaining_seconds,
            'session_state' => array_merge($attempt->session_state ?? [], $data['session_state'] ?? []),
        ]);

        return response()->json(['status' => 'saved']);
    }

    public function finish(PassimarkAttempt $attempt){
        abort_unless($attempt->user_id === Auth::id(), 404);
        if ($attempt->finished_at) {
            return response()->json($this->attemptResult($attempt));
        }
        $result = DB::transaction(function () use ($attempt) {
            $session = $attempt->session;
            if (CatEngine::usesIrt($attempt)) {
                $mle = Irt3PL::mle(CatEngine::answeredParams($attempt), $attempt->theta);
                $theta = $mle['theta'];
                // Prefer a declared theta bar. Both catalog seeders leave theta_required
                // null on every session they create, and reading that as 0 reported a pass
                // against a requirement nobody had set. Where it is absent, fall back to
                // the session's real pass_score percentage rather than to zero. This flag
                // governs lesson completion and unlocking only; CertificateIssuer refuses
                // to mint a credential on an undefined bar.
                $required = $session?->theta_required;
                if ($required !== null) {
                    $passed = $theta >= (float) $required;
                } else {
                    $answered = $attempt->answers()->count();
                    $rawPercent = $answered
                        ? round($attempt->answers()->where('is_correct', true)->count() / $answered * 100, 2)
                        : 0.0;
                    $passed = $rawPercent >= (float) ($session?->pass_score ?? 70);
                }
                $score = Irt3PL::scaledScore($theta);
                $attempt->update(['finished_at'=>now(),'theta'=>$theta,'score'=>$score,'is_passed'=>$passed]);
            } else {
                $score = CatEngine::calculateScore($attempt);
                $passScore = $session?->pass_score ?? 70;
                $passed = $score >= $passScore;
                $attempt->update(['finished_at'=>now(),'score'=>$score,'is_passed'=>$passed]);
            }
            $prog = PassimarkProgress::where('user_id',$attempt->user_id)->where('session_id',$attempt->session_id)->firstOrFail();
            $prog->update(['status'=>$passed?'completed':'open','score'=>$attempt->score,'ability_theta'=>$attempt->theta,'attempts'=>$prog->attempts+1]);
            // Auto-advancement finals certify immediately; approval-gated finals certify on approve.
            if ($attempt->is_passed) {
                \App\Services\CertificateIssuer::issueIfEligible($prog);
            }
            // v4 auto-catalog tracks unlock the next session on a pass; approval-gated tracks
            // (real-content CISSP bundle) stay completed until the learner requests approval.
            if ($attempt->is_passed && optional($session->certificationTrack)->advancement === 'auto') {
                $this->autoUnlockNext($session, $attempt->user_id);
            }
            return $this->attemptResult($attempt->fresh());
        });
        return response()->json($result);
    }

    private function attemptResult(PassimarkAttempt $attempt): array
    {
        $total = $attempt->answers()->count();
        $correct = $attempt->answers()->where('is_correct',true)->count();
        return ['score'=>$attempt->score,'passed'=>(bool) $attempt->is_passed,'theta'=>$attempt->theta,'total'=>$total,'correct'=>$correct];
    }

    /**
     * v4 auto-catalog rule: on tracks with advancement=auto a passed lesson/phase/domain/mock
     * opens the next session in the same certification track (theta/pass gate). Approval-gated
     * tracks (real-content bundle, advancement=approval) never auto-unlock — the learner
     * requests approval and an instructor unlocks the next step. Finals stay approval-gated
     * regardless (certificate issuance, Sprint 8).
     */
    private function autoUnlockNext(PassimarkSession $session, int $userId): void
    {
        \App\Services\Curriculum::unlockNext($session, $userId);
    }
    public function requestApproval(PassimarkSession $session){
        $prog = PassimarkProgress::where('user_id',Auth::id())->where('session_id',$session->id)->firstOrFail();
        abort_unless($prog->status==='completed',400,'Complete session first');
        $prog->update(['status'=>'pending_approval']);
        return back()->with('success','Approval requested. Instructor will unlock next session.');
    }
}
