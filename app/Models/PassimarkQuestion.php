<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkQuestion extends Model {
    protected $table='passimark_questions';
    protected $fillable=['session_id','exam_id','content','options','difficulty','discrimination','guessing','domain','bloom_level','explanation','reference'];
    protected $casts=['options'=>'array'];
    public function tags(){ return $this->belongsToMany(PassimarkTag::class, 'passimark_question_tag', 'question_id', 'tag_id'); }
}
