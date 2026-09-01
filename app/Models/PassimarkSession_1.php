<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkSession extends Model {
    protected $table='passimark_sessions';
    protected $fillable=['number','phase','title','description','domain','is_open','order','pass_score','time_limit','question_count'];
    protected $casts=['is_open'=>'boolean'];
    public function questions(){ return $this->hasMany(PassimarkQuestion::class,'session_id'); }
    public function exams(){ return $this->hasMany(PassimarkExam::class,'session_id'); }
    public function progress(){ return $this->hasMany(PassimarkProgress::class,'session_id'); }
}
