<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkExam extends Model {
    protected $table='passimark_exams';
    protected $fillable=['session_id','title','mode','question_count'];
    public function session(){ return $this->belongsTo(PassimarkSession::class,'session_id'); }
    public function questions(){ return $this->hasMany(PassimarkQuestion::class,'exam_id'); }
}
