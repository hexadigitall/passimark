<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PassimarkSession extends Model {
    protected $table='passimark_sessions';
    protected $fillable=['external_id','certification_track_id','cert_slug','number','phase','phase_type','title','description','domain','is_open','is_optional','order','pass_score','theta_required','time_limit','time_minutes','question_count','questions_target'];
    protected $casts=['is_open'=>'boolean','is_optional'=>'boolean','theta_required'=>'float','time_minutes'=>'integer','questions_target'=>'integer'];
    public function certificationTrack(){ return $this->belongsTo(PassimarkCertificationTrack::class,'certification_track_id'); }
    public function tags(){ return $this->belongsToMany(PassimarkTag::class, 'passimark_session_tag', 'session_id', 'tag_id'); }
    public function questions(){ return $this->hasMany(PassimarkQuestion::class,'session_id'); }
    public function exams(){ return $this->hasMany(PassimarkExam::class,'session_id'); }
    public function progress(){ return $this->hasMany(PassimarkProgress::class,'session_id'); }

    public function scopeStage(Builder $q, string $phaseType): Builder { return $q->where('phase_type',$phaseType); }
    public function scopeLessons(Builder $q): Builder { return $q->stage('lesson'); }
    public function scopePhases(Builder $q): Builder { return $q->stage('phase'); }
    public function scopeDomains(Builder $q): Builder { return $q->stage('domain'); }
    public function scopeMocks(Builder $q): Builder { return $q->stage('mock'); }
    public function scopeFinals(Builder $q): Builder { return $q->stage('final'); }
    public function scopeCert(Builder $q): Builder { return $q->stage('cert'); }
    public function scopeByCert(Builder $q, string $certSlug): Builder { return $q->where('cert_slug',$certSlug); }
}
