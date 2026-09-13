<?php
namespace App\Http\Controllers;
use App\Models\{PassimarkSession, PassimarkExam, PassimarkQuestion, PassimarkAttempt, PassimarkProgress};
use App\Services\CatEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PassimarkController extends Controller
{
    public function dashboard(){
        $sessions = PassimarkSession::orderBy('order')->get();
        $progress = PassimarkProgress::where('user_id', Auth::id())->get()->keyBy('session_id');
        if($progress->isEmpty()){
            PassimarkProgress::create(['user_id'=>Auth::id(),'session_id'=>1,'status'=>'open']);
            $progress = PassimarkProgress::where('user_id', Auth::id())->get()->keyBy('session_id');
        }
        return Inertia::render('Passimark/Dashboard', compact('sessions','progress'));
    }

    public function profile()
    {
        return Inertia::render('Passimark/Profile', [
            'user' => Auth::user(),
        ]);
    }

    public function settings()
    {
        return Inertia::render('Passimark/Settings', [
            'user' => Auth::user(),
        ]);
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

        return Inertia::render('Passimark/Exam', [
            'attempt' => $attempt->load('session', 'exam'),
            'question' => CatEngine::nextQuestion($attempt),
            'answeredCount' => $attempt->answers()->count(),
        ]);
    }

    public function result(PassimarkAttempt $attempt)
    {
        abort_unless($attempt->user_id === Auth::id() && $attempt->finished_at, 404);

        return Inertia::render('Passimark/Result', [
            'attempt' => $attempt->load('session', 'exam'),
            'answers' => $attempt->answers()->with('question')->get(),
            'history' => PassimarkAttempt::where('user_id', Auth::id())
                ->where('session_id', $attempt->session_id)
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->get(['id', 'mode', 'score', 'is_passed', 'finished_at']),
        ]);
    }

    public function start(Request $r, PassimarkSession $session){
        $prog = PassimarkProgress::where('user_id',Auth::id())->where('session_id',$session->id)->firstOrFail();
        abort_unless(in_array($prog->status,['open','approved','in_progress','completed']),403,'Session locked. Awaiting instructor approval.');
        $mode = $r->validate(['mode' => ['nullable', Rule::in(['cat', 'timed', 'practice'])]])['mode'] ?? 'cat';
        $exam = $session->exams()->firstOrCreate(['mode'=>$mode],['title'=>"{$session->title} - ".strtoupper($mode),'question_count'=>$mode==='cat'?150:25]);
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
        $r->validate(['question_id'=>'required|integer','selected'=>'required|string','time_spent'=>'nullable|integer|min:0']);
        $q = PassimarkQuestion::findOrFail($r->question_id);
        abort_unless($q->session_id === $attempt->session_id, 422, 'Question does not belong to this assessment.');
        abort_unless($q->id === CatEngine::nextQuestion($attempt)?->id, 422, 'Question is not the current assessment question.');
        abort_unless(collect($q->options)->pluck('key')->contains($r->selected), 422, 'Selected option is invalid.');
        $correctKey = collect($q->options)->firstWhere('is_correct',true)['key'] ?? null;
        $isCorrect = $r->selected === $correctKey;
        if($attempt->mode==='cat'){ $attempt->updateTheta($isCorrect,$q->difficulty,$q->discrimination,$q->guessing); }
        $attempt->answers()->create(['question_id'=>$q->id,'selected_option'=>$r->selected,'is_correct'=>$isCorrect,'time_spent'=>$r->time_spent??0]);
        $next = CatEngine::nextQuestion($attempt);
        if(CatEngine::shouldTerminate($attempt) || !$next){ return $this->finish($attempt); }
        return response()->json(['correct'=>$isCorrect,'explanation'=>$attempt->mode==='practice'?$q->explanation:null,'next'=>$next,'theta'=>$attempt->theta,'answeredCount'=>$attempt->answers()->count()]);
    }
    public function finish(PassimarkAttempt $attempt){
        abort_unless($attempt->user_id === Auth::id(), 404);
        if ($attempt->finished_at) {
            return response()->json($this->attemptResult($attempt));
        }
        $result = DB::transaction(function () use ($attempt) {
            $score = CatEngine::calculateScore($attempt);
            $attempt->update(['finished_at'=>now(),'score'=>$score,'is_passed'=>$score>=70]);
            $prog = PassimarkProgress::where('user_id',$attempt->user_id)->where('session_id',$attempt->session_id)->firstOrFail();
            $prog->update(['status'=>$score>=70?'completed':'open','score'=>$score,'ability_theta'=>$attempt->theta,'attempts'=>$prog->attempts+1]);
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
    public function requestApproval(PassimarkSession $session){
        $prog = PassimarkProgress::where('user_id',Auth::id())->where('session_id',$session->id)->firstOrFail();
        abort_unless($prog->status==='completed',400,'Complete session first');
        $prog->update(['status'=>'pending_approval']);
        return back()->with('success','Approval requested. Instructor will unlock next session.');
    }
}
