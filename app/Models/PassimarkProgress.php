<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkProgress extends Model {
    protected $table='passimark_progress';
    protected $fillable=['user_id','session_id','status','score','ability_theta','attempts'];
    const LOCKED='locked'; const OPEN='open'; const IN_PROGRESS='in_progress';
    const COMPLETED='completed'; const PENDING='pending_approval'; const APPROVED='approved';
    public function user(){ return $this->belongsTo(User::class); }
    public function session(){ return $this->belongsTo(PassimarkSession::class,'session_id'); }
}
