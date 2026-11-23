<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkAttemptAnswer extends Model {
    protected $table='passimark_attempt_answers';
    protected $fillable=['attempt_id','question_id','selected_option','interaction_response','is_correct','time_spent'];
    protected $casts=['interaction_response'=>'array'];
    public function question(){ return $this->belongsTo(PassimarkQuestion::class,'question_id'); }
}
