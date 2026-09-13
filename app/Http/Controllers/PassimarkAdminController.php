<?php
namespace App\Http\Controllers;
use App\Models\{PassimarkSession, PassimarkProgress, PassimarkQuestion, PassimarkApprovalEvent, PassimarkExam, PassimarkAttempt, User, PassimarkCertificationTrack};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PassimarkAdminController extends Controller
{
    public function index(){ 
        $pending = PassimarkProgress::with(['user','session'])->where('status','pending_approval')->get();
        $sessions = PassimarkSession::with(['exams', 'questions', 'certificationTrack'])->orderBy('order')->get();
        $tracks = PassimarkCertificationTrack::withCount('sessions')->orderBy('title')->get();
        $events = PassimarkApprovalEvent::with(['reviewer','progress.user','progress.session'])->latest()->limit(20)->get();
        $report = [
            'learners' => User::where('role', 'student')->count(),
            'attempts' => PassimarkAttempt::count(),
            'completed_attempts' => PassimarkAttempt::whereNotNull('finished_at')->count(),
            'average_score' => round((float) PassimarkAttempt::whereNotNull('score')->avg('score'), 2),
            'pending_approvals' => $pending->count(),
        ];
        return Inertia::render('Passimark/Admin', compact('pending','sessions','events','report','tracks'));
    }
    public function approve(Request $request, PassimarkProgress $progress){
        abort_unless($progress->status === PassimarkProgress::PENDING, 422, 'Progress is not awaiting approval.');
        $data = $request->validate(['note'=>'nullable|string|max:2000']);
        $progress->update(['status'=>'approved']);
        PassimarkApprovalEvent::create(['progress_id'=>$progress->id,'reviewer_id'=>$request->user()->id,'action'=>'approved','note'=>$data['note'] ?? null]);
        $next = PassimarkSession::where('order','>',$progress->session->order)->orderBy('order')->first();
        if($next){
            PassimarkProgress::firstOrCreate(['user_id'=>$progress->user_id,'session_id'=>$next->id],['status'=>'open']);
        }
        return response()->json(['message'=>$next ? "Approved. Session {$next->number} unlocked" : 'Approved. Final session completed.']);
    }
    public function reject(Request $request, PassimarkProgress $progress){
        abort_unless($progress->status === PassimarkProgress::PENDING, 422, 'Progress is not awaiting approval.');
        $data = $request->validate(['note'=>'required|string|max:2000']);
        $progress->update(['status'=>'completed']);
        PassimarkApprovalEvent::create(['progress_id'=>$progress->id,'reviewer_id'=>$request->user()->id,'action'=>'rejected','note'=>$data['note']]);
        return response()->json(['message'=>'Rejected - returned to completed']);
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
        ]);
        $count=0;
        foreach($data['questions'] as $q){
            abort_unless(collect($q['options'])->contains('is_correct', true), 422, 'Each question must have at least one correct option.');
            PassimarkQuestion::create([
                'session_id'=>$data['session_id'],
                'content'=>$q['content'],
                'options'=>$q['options'],
                'difficulty'=>$q['difficulty']??0,
                'domain'=>$q['domain']??'General',
                'explanation'=>$q['explanation']??'',
                'bloom_level'=>$q['bloom']??'Apply'
            ]); $count++;
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
        $session = PassimarkSession::create($this->sessionData($request));
        return response()->json(['data' => $session], 201);
    }

    public function updateSession(Request $request, PassimarkSession $session)
    {
        $session->update($this->sessionData($request, true));
        return response()->json(['data' => $session->fresh()]);
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

    public function storeQuestion(Request $request)
    {
        $question = PassimarkQuestion::create($this->questionData($request));
        return response()->json(['data' => $question], 201);
    }

    public function updateQuestion(Request $request, PassimarkQuestion $question)
    {
        $question->update($this->questionData($request, true));
        return response()->json(['data' => $question->fresh()]);
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
            'domain' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'bloom_level' => ['nullable', 'string', 'max:255'],
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
}
