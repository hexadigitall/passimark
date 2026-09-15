<?php
namespace App\Http\Controllers;
use App\Models\{PassimarkSession, PassimarkProgress, PassimarkQuestion, PassimarkApprovalEvent, PassimarkExam, PassimarkAttempt, User, PassimarkCertificationTrack, PassimarkTag};
use App\Services\AdminAnalytics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PassimarkAdminController extends Controller
{
    /**
     * Admin/instructor landing at "/" — operations overview, not the learner path.
     * The full content-management Control Center stays at /admin/passimark (index()).
     */
    public function dashboard()
    {
        $analytics = new AdminAnalytics;

        $pending = PassimarkProgress::with(['user', 'session'])->where('status', 'pending_approval')->get();
        $events = PassimarkApprovalEvent::with(['progress.user', 'progress.session'])->latest()->limit(20)->get();
        $tiles = $analytics->tiles();
        $by = array_column($tiles, 'value', 'key');
        $report = [
            'learners' => $by['learners'],
            'attempts' => $by['attempts'],
            'completed_attempts' => $by['completed'],
            'average_score' => $by['average_score'],
            'approved_7d' => $by['approvals_7d'],
            'completions' => $by['completions'],
            'sessions' => $by['sessions'],
            'questions' => $by['questions'],
            'tracks' => $by['tracks'],
            'pending_approvals' => $by['pending'],
        ];
        $needsAttention = [
            'contentless_sessions' => PassimarkSession::doesntHave('questions')->count(),
            'empty_tracks' => PassimarkCertificationTrack::withCount('sessions')->get()->where('sessions_count', 0)->count(),
            'untagged_questions' => PassimarkQuestion::doesntHave('tags')->count(),
        ];

        return Inertia::render('Passimark/AdminDashboard', compact('pending', 'events', 'report', 'tiles', 'needsAttention'));
    }

    public function reportLearners()
    {
        return Inertia::render('Passimark/Reports/Learners', app(AdminAnalytics::class)->learners());
    }

    public function reportAttempts(Request $request)
    {
        $analytics = app(AdminAnalytics::class);
        return Inertia::render('Passimark/Reports/Attempts', $analytics->attempts($request->string('status')->toString(), $request->string('mode')->toString()));
    }

    public function reportSessions()
    {
        return Inertia::render('Passimark/Reports/Sessions', app(AdminAnalytics::class)->sessions());
    }

    public function reportQuestions()
    {
        return Inertia::render('Passimark/Reports/Questions', app(AdminAnalytics::class)->questions());
    }

    public function reportTracks()
    {
        return Inertia::render('Passimark/Reports/Tracks', app(AdminAnalytics::class)->tracks());
    }

    public function reportApprovals(Request $request)
    {
        $analytics = app(AdminAnalytics::class);
        return Inertia::render('Passimark/Reports/Approvals', $analytics->approvals($request->string('filter', 'all')->toString()));
    }

    public function index(){ 
        $pending = PassimarkProgress::with(['user','session'])->where('status','pending_approval')->get();
        $sessions = PassimarkSession::with(['exams', 'questions.tags', 'certificationTrack', 'tags'])->orderBy('order')->get();
        $tracks = PassimarkCertificationTrack::withCount('sessions')->orderBy('title')->get();
        $tags = PassimarkTag::withCount(['questions', 'sessions'])->orderBy('type')->orderBy('label')->get();
        $events = PassimarkApprovalEvent::with(['reviewer','progress.user','progress.session'])->latest()->limit(20)->get();
        $report = [
            'learners' => User::where('role', 'student')->count(),
            'attempts' => PassimarkAttempt::count(),
            'completed_attempts' => PassimarkAttempt::whereNotNull('finished_at')->count(),
            'average_score' => round((float) PassimarkAttempt::whereNotNull('score')->avg('score'), 2),
            'pending_approvals' => $pending->count(),
        ];
        return Inertia::render('Passimark/Admin', compact('pending','sessions','events','report','tracks','tags'));
    }
    public function approve(Request $request, PassimarkProgress $progress){
        $data = $request->validate(['note'=>'nullable|string|max:2000']);
        if ($progress->status !== PassimarkProgress::PENDING) {
            return response()->json([
                'status' => 'skipped',
                'message' => $progress->status === PassimarkProgress::APPROVED
                    ? 'This submission was already approved.'
                    : "This submission is no longer awaiting approval (status: {$progress->status}).",
            ]);
        }
        $progress->update(['status'=>'approved']);
        PassimarkApprovalEvent::create(['progress_id'=>$progress->id,'reviewer_id'=>$request->user()->id,'action'=>'approved','note'=>$data['note'] ?? null]);
        $next = \App\Services\Curriculum::unlockNext($progress->session, $progress->user_id);
        return response()->json(['status'=>'approved','message'=>$next ? "Approved. Session {$next->number} unlocked" : 'Approved. Track completed.']);
    }
    public function reject(Request $request, PassimarkProgress $progress){
        $data = $request->validate(['note'=>'required|string|max:2000']);
        if ($progress->status !== PassimarkProgress::PENDING) {
            return response()->json([
                'status' => 'skipped',
                'message' => "This submission is no longer awaiting approval (status: {$progress->status}).",
            ]);
        }
        $progress->update(['status'=>'completed']);
        PassimarkApprovalEvent::create(['progress_id'=>$progress->id,'reviewer_id'=>$request->user()->id,'action'=>'rejected','note'=>$data['note']]);
        return response()->json(['status'=>'rejected','message'=>'Rejected - returned to completed.']);
    }
    public function importQuestions(Request $r){
        $data = $r->validate([
            'session_id'=>'required|exists:passimark_sessions,id',
            'questions'=>'required|array|min:1',
            'questions.*.content'=>'required|string',
            'questions.*.options'=>'required|array|min:2',
            'questions.*.options.*.key'=>'required|string|distinct',
            'questions.*.options.*.text'=>'required|string',
            'questions.*.options.*.is_correct'=>'required|boolean',
            'questions.*.difficulty'=>'nullable|numeric',
            'questions.*.domain'=>'required|string|max:255',
            'questions.*.explanation'=>'nullable|string',
            'questions.*.bloom'=>'nullable|string|max:255',
            'questions.*.tag_ids'=>'nullable|array',
            'questions.*.tag_ids.*'=>'integer|exists:passimark_tags,id',
        ]);
        $count=0;
        foreach($data['questions'] as $q){
            abort_unless(collect($q['options'])->contains('is_correct', true), 422, 'Each question must have at least one correct option.');
            $question = PassimarkQuestion::create([
                'session_id'=>$data['session_id'],
                'content'=>$q['content'],
                'options'=>$q['options'],
                'difficulty'=>$q['difficulty']??0,
                'domain'=>$q['domain']??'General',
                'explanation'=>$q['explanation']??'',
                'bloom_level'=>$q['bloom']??'Apply'
            ]);
            $question->tags()->sync($q['tag_ids'] ?? []);
            $count++;
        }
        return response()->json(['imported'=>$count]);
    }

    public function importPage()
    {
        return Inertia::render('Passimark/ContentImport', [
            'sessions' => PassimarkSession::orderBy('order')->get(['id', 'number', 'title']),
        ]);
    }

    public function storeSession(Request $request)
    {
        $data = $this->sessionData($request);
        $session = PassimarkSession::create($data);
        if (array_key_exists('tag_ids', $data)) {
            $session->tags()->sync($data['tag_ids']);
        }
        return response()->json(['data' => $session->load('tags')], 201);
    }

    public function updateSession(Request $request, PassimarkSession $session)
    {
        $data = $this->sessionData($request, true);
        $session->update($data);
        if (array_key_exists('tag_ids', $data)) {
            $session->tags()->sync($data['tag_ids']);
        }
        return response()->json(['data' => $session->fresh()->load('tags')]);
    }

    public function destroySession(PassimarkSession $session)
    {
        $session->delete();
        return response()->json([], 204);
    }

    public function storeCertificationTrack(Request $request)
    {
        $track = PassimarkCertificationTrack::create($this->certificationTrackData($request));
        return response()->json(['data' => $track], 201);
    }

    public function updateCertificationTrack(Request $request, PassimarkCertificationTrack $certificationTrack)
    {
        $certificationTrack->update($this->certificationTrackData($request, true));
        return response()->json(['data' => $certificationTrack->fresh()]);
    }

    public function destroyCertificationTrack(PassimarkCertificationTrack $certificationTrack)
    {
        abort_if($certificationTrack->sessions()->exists(), 422, 'Reassign or remove this track\'s sessions before deleting it.');
        $certificationTrack->delete();
        return response()->json([], 204);
    }

    public function storeExam(Request $request)
    {
        $data = $this->examData($request);
        $exam = PassimarkExam::create($data);
        return response()->json(['data' => $exam], 201);
    }

    public function updateExam(Request $request, PassimarkExam $exam)
    {
        $exam->update($this->examData($request, true));
        return response()->json(['data' => $exam->fresh()]);
    }

    public function destroyExam(PassimarkExam $exam)
    {
        $exam->delete();
        return response()->json([], 204);
    }

    public function storeTag(Request $request)
    {
        $tag = PassimarkTag::create($this->tagData($request));
        return response()->json(['data' => $tag], 201);
    }

    public function updateTag(Request $request, PassimarkTag $tag)
    {
        $tag->update($this->tagData($request, true));
        return response()->json(['data' => $tag->fresh()]);
    }

    public function destroyTag(PassimarkTag $tag)
    {
        abort_if($tag->questions()->exists() || $tag->sessions()->exists(), 422, 'Reassign or remove this tag\'s content before deleting it.');
        $tag->delete();
        return response()->json([], 204);
    }

    public function storeQuestion(Request $request)
    {
        $data = $this->questionData($request);
        $question = PassimarkQuestion::create($data);
        if (array_key_exists('tag_ids', $data)) {
            $question->tags()->sync($data['tag_ids']);
        }
        if (empty($data['domain'] ?? null) && ($domainTag = $question->tags()->where('type', 'domain')->first())) {
            $question->update(['domain' => $domainTag->label]);
        }
        return response()->json(['data' => $question->fresh()->load('tags')], 201);
    }

    public function updateQuestion(Request $request, PassimarkQuestion $question)
    {
        $data = $this->questionData($request, true);
        $question->update($data);
        if (array_key_exists('tag_ids', $data)) {
            $question->tags()->sync($data['tag_ids']);
        }
        return response()->json(['data' => $question->fresh()->load('tags')]);
    }

    public function destroyQuestion(PassimarkQuestion $question)
    {
        $question->delete();
        return response()->json([], 204);
    }

    private function sessionData(Request $request, bool $partial = false): array
    {
        $rules = [
            'certification_track_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:passimark_certification_tracks,id'],
            'number' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'phase' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'domain' => ['nullable', 'string', 'max:255'],
            'is_open' => ['sometimes', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:passimark_tags,id'],
            'order' => [$partial ? 'sometimes' : 'required', 'integer', 'min:1'],
            'pass_score' => ['sometimes', 'integer', 'between:1,100'],
            'time_limit' => ['sometimes', 'integer', 'min:1'],
            'question_count' => ['sometimes', 'integer', 'min:1'],
        ];
        return $request->validate($rules);
    }

    private function examData(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'session_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:passimark_sessions,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'mode' => [$partial ? 'sometimes' : 'required', Rule::in(['cat', 'timed', 'practice'])],
            'question_count' => ['sometimes', 'integer', 'min:1'],
        ]);
        return $data;
    }

    private function questionData(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'session_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:passimark_sessions,id'],
            'exam_id' => ['nullable', 'integer', 'exists:passimark_exams,id'],
            'content' => [$partial ? 'sometimes' : 'required', 'string'],
            'options' => [$partial ? 'sometimes' : 'required', 'array', 'min:2'],
            'options.*.key' => ['required', 'string', 'distinct'],
            'options.*.text' => ['required', 'string'],
            'options.*.is_correct' => ['required', 'boolean'],
            'difficulty' => ['sometimes', 'numeric'],
            'discrimination' => ['sometimes', 'numeric', 'min:0'],
            'guessing' => ['sometimes', 'numeric', 'between:0,1'],
            'domain' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:255'],
            'bloom_level' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:passimark_tags,id'],
            'explanation' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        if (array_key_exists('options', $data) && !collect($data['options'])->contains('is_correct', true)) {
            abort(422, 'At least one option must be correct.');
        }
        if (!empty($data['exam_id'])) {
            $exam = PassimarkExam::findOrFail($data['exam_id']);
            if (!empty($data['session_id'])) {
                abort_unless($exam->session_id === (int) $data['session_id'], 422, 'Exam does not belong to this session.');
            } elseif ($partial) {
                $data['session_id'] = $exam->session_id;
            }
        }
        return $data;
    }

    private function certificationTrackData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'slug' => [$partial ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('passimark_certification_tracks', 'slug')->ignore($request->route('certificationTrack'))],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function tagData(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'type' => [$partial ? 'sometimes' : 'required', Rule::in(PassimarkTag::TYPES)],
            'label' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
        ]);
        if (array_key_exists('label', $data) && $data['label'] !== '') {
            $slug = Str::slug($data['label']);
            $type = $data['type'] ?? $request->route('tag')?->type ?? 'domain';
            $query = PassimarkTag::where('type', $type)->where('slug', $slug);
            if ($current = $request->route('tag')) {
                $query->where('id', '!=', $current->id);
            }
            abort_if($query->exists(), 422, 'A tag with this label already exists for this type.');
            $data['slug'] = $slug;
        }
        return $data;
    }
}
