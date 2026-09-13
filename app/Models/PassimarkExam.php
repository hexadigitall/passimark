<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkExam extends Model {
    protected $table='passimark_exams';
    protected $fillable=['session_id','title','mode','question_count','time_minutes','is_final','irt_enabled'];
    protected $casts=['is_final'=>'boolean','irt_enabled'=>'boolean'];
    public function session(){ return $this->belongsTo(PassimarkSession::class,'session_id'); }
    public function questions(){ return $this->hasMany(PassimarkQuestion::class,'exam_id'); }
}
