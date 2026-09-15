<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassimarkPackageImport extends Model
{
    protected $table = 'passimark_package_imports';
    protected $fillable = ['uploaded_by', 'original_filename', 'package_id', 'checksum', 'content_type', 'summary', 'status', 'error_report'];
    protected $casts = ['summary' => 'array', 'error_report' => 'array'];
}