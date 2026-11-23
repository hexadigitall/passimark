<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassimarkImportBatch extends Model
{
    use HasFactory;

    protected $table = 'passimark_import_batches';

    protected $fillable = [
        'user_id',
        'source_type',
        'original_filename',
        'file_path',
        'checksum',
        'status',
        'total_items',
        'valid_items',
        'error_items',
        'metadata',
        'error_log',
    ];

    protected $casts = [
        'metadata' => 'array',
        'error_log' => 'array',
        'total_items' => 'integer',
        'valid_items' => 'integer',
        'error_items' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PassimarkImportItem::class, 'batch_id')->orderBy('source_index');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
