<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkTag extends Model {
    public const TYPES = ['domain', 'bloom', 'skill'];
    protected $table = 'passimark_tags';
    protected $fillable = ['type', 'label', 'slug'];
    public function questions(){ return $this->belongsToMany(PassimarkQuestion::class, 'passimark_question_tag', 'tag_id', 'question_id'); }
    public function sessions(){ return $this->belongsToMany(PassimarkSession::class, 'passimark_session_tag', 'tag_id', 'session_id'); }
}