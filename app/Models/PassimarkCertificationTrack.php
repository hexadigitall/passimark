<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkCertificationTrack extends Model {
    protected $table='passimark_certification_tracks';
    protected $fillable=['slug','title','description','is_active'];
    protected $casts=['is_active'=>'boolean'];
    public function sessions(){ return $this->hasMany(PassimarkSession::class,'certification_track_id'); }
}
