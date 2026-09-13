<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkSession extends Model {
    protected $table='passimark_sessions';
    protected $fillable=['certification_track_id','number','phase','title','description','domain','is_open','order','pass_score','time_limit','question_count'];
    protected $casts=['is_open'=>'boolean'];
    public function certificationTrack(){ return $this->belongsTo(PassimarkCertificationTrack::class,'certification_track_id'); }
    public function tags(){ return $this->belongsToMany(PassimarkTag::class, 'passimark_session_tag', 'session_id', 'tag_id'); }
    public function questions(){ return $this->hasMany(PassimarkQuestion::class,'session_id'); }
    public function exams(){ return $this->hasMany(PassimarkExam::class,'session_id'); }
    public function progress(){ return $this->hasMany(PassimarkProgress::class,'session_id'); }
}
