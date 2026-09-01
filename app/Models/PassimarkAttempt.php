<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkAttempt extends Model {
    protected $table='passimark_attempts';
    protected $fillable=['user_id','session_id','exam_id','mode','theta','started_at','finished_at','score','is_passed','responses'];
    protected $casts=['responses'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];
    public function answers(){ return $this->hasMany(PassimarkAttemptAnswer::class,'attempt_id'); }
    public function session(){ return $this->belongsTo(PassimarkSession::class,'session_id'); }
    public function exam(){ return $this->belongsTo(PassimarkExam::class,'exam_id'); }
    public function updateTheta(bool $correct, float $b, float $a=1.0, float $c=0.25){
        $p = $c + (1-$c) / (1 + exp(-$a*($this->theta - $b)));
        $this->theta += 0.35 * $a * (($correct?1:0) - $p);
        $this->save(); return $this->theta;
    }
}
