<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PassimarkQuestion extends Model {
    protected $table='passimark_questions';
    protected $fillable=['session_id','exam_id','content','options','correct_key','difficulty','discrimination','guessing','domain','bloom_level','explanation','reference'];
    protected $casts=['options'=>'array'];
    public function tags(){ return $this->belongsToMany(PassimarkTag::class, 'passimark_question_tag', 'question_id', 'tag_id'); }

    // IRT aliases (v4 canonical naming) backed by the existing a/b/c columns.
    public function getADiscriminationAttribute(): ?float { return $this->discrimination; }
    public function setADiscriminationAttribute($value): void { $this->discrimination = $value; }
    public function getBDifficultyAttribute(): ?float { return $this->difficulty; }
    public function setBDifficultyAttribute($value): void { $this->difficulty = $value; }
    public function getCGuessingAttribute(): ?float { return $this->guessing; }
    public function setCGuessingAttribute($value): void { $this->guessing = $value; }
}
