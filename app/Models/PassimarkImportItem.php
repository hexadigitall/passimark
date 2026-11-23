<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassimarkImportItem extends Model
{
    use HasFactory;

    protected $table = 'passimark_import_items';

    protected $fillable = [
        'batch_id',
        'source_index',
        'item_type',
        'prompt',
        'choices',
        'correct_answers',
        'explanation',
        'reference',
        'domain',
        'bloom',
        'difficulty',
        'discrimination',
        'guessing',
        'interactive_payload',
        'exhibits',
        'status',
        'validation_errors',
    ];

    protected $casts = [
        'choices' => 'array',
        'correct_answers' => 'array',
        'interactive_payload' => 'array',
        'exhibits' => 'array',
        'validation_errors' => 'array',
        'difficulty' => 'float',
        'discrimination' => 'float',
        'guessing' => 'float',
        'source_index' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PassimarkImportBatch::class, 'batch_id');
    }
}
