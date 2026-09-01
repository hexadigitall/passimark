<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkAttemptAnswer extends Model {
    protected $table='passimark_attempt_answers';
    protected $fillable=['attempt_id','question_id','selected_option','is_correct','time_spent'];
}
