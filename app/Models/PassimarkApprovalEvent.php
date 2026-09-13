<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassimarkApprovalEvent extends Model
{
    protected $table = 'passimark_approval_events';

    protected $fillable = ['progress_id', 'reviewer_id', 'action', 'note'];

    public function progress()
    {
        return $this->belongsTo(PassimarkProgress::class, 'progress_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
