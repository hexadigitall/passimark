<?php
namespace App\Http\Controllers;
use App\Models\{PassimarkSession, PassimarkProgress, PassimarkQuestion};
use Illuminate\Http\Request;
use Inertia\Inertia;

class PassimarkAdminController extends Controller
{
    public function index(){ 
        $pending = PassimarkProgress::with(['user','session'])->where('status','pending_approval')->get();
        $sessions = PassimarkSession::orderBy('order')->get();
        return Inertia::render('Passimark/Admin', compact('pending','sessions'));
    }
    public function approve(PassimarkProgress $progress){
        $progress->update(['status'=>'approved']);
        $next = PassimarkSession::where('order','>',$progress->session->order)->orderBy('order')->first();
        if($next){
            PassimarkProgress::firstOrCreate(['user_id'=>$progress->user_id,'session_id'=>$next->id],['status'=>'open']);
        }
        return response()->json(['message'=>"Approved. Session {$next->number} unlocked"]);
    }
    public function reject(PassimarkProgress $progress){
        $progress->update(['status'=>'completed']);
        return response()->json(['message'=>'Rejected - returned to completed']);
    }
    public function importQuestions(Request $r){
        $data = $r->validate(['session_id'=>'required|exists:passimark_sessions,id','questions'=>'required|array']);
        $count=0;
        foreach($data['questions'] as $q){
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
}
