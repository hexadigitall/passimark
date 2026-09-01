<?php
namespace App\Http\Controllers;
use App\Models\{PassimarkSession, PassimarkExam, PassimarkQuestion, PassimarkAttempt, PassimarkProgress};
use App\Services\CatEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function start(Request $r, PassimarkSession $session){
        $prog = PassimarkProgress::where('user_id',Auth::id())->where('session_id',$session->id)->firstOrFail();
        abort_unless(in_array($prog->status,['open','approved','in_progress','completed']),403,'Session locked. Awaiting instructor approval.');
        $mode = $r->input('mode','cat');
        $exam = $session->exams()->firstOrCreate(['mode'=>$mode],['title'=>"{$session->title} - ".strtoupper($mode),'question_count'=>$mode==='cat'?150:25]);
        $attempt = PassimarkAttempt::create(['user_id'=>Auth::id(),'session_id'=>$session->id,'exam_id'=>$exam->id,'mode'=>$mode,'theta'=>$prog->ability_theta,'started_at'=>now()]);
        $prog->update(['status'=>'in_progress']);
        $next = CatEngine::nextQuestion($attempt);
        return response()->json(['attempt'=>$attempt,'question'=>$next]);
    }
    public function answer(Request $r, PassimarkAttempt $attempt){
        $r->validate(['question_id'=>'required','selected'=>'required','time_spent'=>'nullable|integer']);
        $q = PassimarkQuestion::findOrFail($r->question_id);
        $correctKey = collect($q->options)->firstWhere('is_correct',true)['key'] ?? null;
        $isCorrect = $r->selected === $correctKey;
        if($attempt->mode==='cat'){ $attempt->updateTheta($isCorrect,$q->difficulty,$q->discrimination,$q->guessing); }
        $attempt->answers()->create(['question_id'=>$q->id,'selected_option'=>$r->selected,'is_correct'=>$isCorrect,'time_spent'=>$r->time_spent??0]);
        if(CatEngine::shouldTerminate($attempt)){ return $this->finish($attempt); }
        $next = CatEngine::nextQuestion($attempt);
        return response()->json(['correct'=>$isCorrect,'explanation'=>$attempt->mode==='practice'?$q->explanation:null,'next'=>$next,'theta'=>$attempt->theta]);
    }
    public function finish(PassimarkAttempt $attempt){
        $score = CatEngine::calculateScore($attempt);
        $attempt->update(['finished_at'=>now(),'score'=>$score,'is_passed'=>$score>=70]);
        $prog = PassimarkProgress::where('user_id',$attempt->user_id)->where('session_id',$attempt->session_id)->first();
        $prog->update(['status'=>$score>=70?'completed':'open','score'=>$score,'ability_theta'=>$attempt->theta,'attempts'=>$prog->attempts+1]);
        return response()->json(['score'=>$score,'passed'=>$score>=70,'theta'=>$attempt->theta,'total'=>$attempt->answers()->count(),'correct'=>$attempt->answers()->where('is_correct',true)->count()]);
    }
    public function requestApproval(PassimarkSession $session){
        $prog = PassimarkProgress::where('user_id',Auth::id())->where('session_id',$session->id)->firstOrFail();
        abort_unless($prog->status==='completed',400,'Complete session first');
        $prog->update(['status'=>'pending_approval']);
        return back()->with('success','Approval requested. Instructor will unlock next session.');
    }
}
