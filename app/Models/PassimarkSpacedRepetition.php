<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassimarkSpacedRepetition extends Model
{
    protected $table = 'passimark_spaced_repetitions';

    protected $fillable = [
        'user_id',
        'question_id',
        'box',
        'consecutive_correct',
        'review_count',
        'last_reviewed_at',
        'next_review_at',
        'difficulty_modifier',
    ];

    protected $casts = [
        'box' => 'integer',
        'consecutive_correct' => 'integer',
        'review_count' => 'integer',
        'last_reviewed_at' => 'datetime',
        'next_review_at' => 'datetime',
        'difficulty_modifier' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PassimarkQuestion::class, 'question_id');
    }
}
