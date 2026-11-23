<?php
namespace App\Models;
use App\Services\Irt\Irt3PL;
use Illuminate\Database\Eloquent\Model;
class PassimarkAttempt extends Model {
    protected $table='passimark_attempts';
    protected $fillable=['user_id','session_id','exam_id','mode','theta','started_at','finished_at','paused_at','remaining_seconds','session_state','score','is_passed','responses'];
    protected $casts=['responses'=>'array','session_state'=>'array','started_at'=>'datetime','finished_at'=>'datetime','paused_at'=>'datetime'];
    public function answers(){ return $this->hasMany(PassimarkAttemptAnswer::class,'attempt_id'); }
    public function user(){ return $this->belongsTo(User::class,'user_id'); }
    public function session(){ return $this->belongsTo(PassimarkSession::class,'session_id'); }
    public function exam(){ return $this->belongsTo(PassimarkExam::class,'exam_id'); }
    /**
     * Move the ability estimate after a response.
     *
     * Authored bundles deliberately carry no measured item parameters (see
     * docs/content-format.md), so b/a/c arrive as null on every question. The IRT
     * path normalises that through Irt3PL::params() and its documented defaults;
     * this path must not be stricter than that one, or a legacy CAT attempt dies
     * with a TypeError on its first answer instead of scoring it.
     */
    public function updateTheta(bool $correct, ?float $b = null, ?float $a = null, ?float $c = null): float {
        $params = Irt3PL::params(['difficulty'=>$b,'discrimination'=>$a,'guessing'=>$c]);
        $b = $params['b'];
        $a = $params['a'];
        $c = $params['c'];
        $theta = (float) ($this->theta ?? 0.0);
        $p = $c + (1-$c) / (1 + exp(-$a*($theta - $b)));
        $theta += 0.35 * $a * (($correct?1:0) - $p);
        $this->theta = $theta;
        $this->save();
        return $theta;
    }
}
