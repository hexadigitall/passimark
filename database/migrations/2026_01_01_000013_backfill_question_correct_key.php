<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Legacy CISSP pools stored correctness only in the option `is_correct` flag, leaving the
     * explicit `correct_key` column (added in 000006) null. Backfill it from the flagged option
     * so every question row carries a first-class answer key. No-op where already populated.
     */
    public function up(): void
    {
        DB::table('passimark_questions')
            ->whereNull('correct_key')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) {
                foreach ($rows as $row) {
                    $options = json_decode($row->options, true);
                    if (!is_array($options)) {
                        continue;
                    }
                    foreach ($options as $option) {
                        if (!empty($option['is_correct']) && !empty($option['key'])) {
                            DB::table('passimark_questions')
                                ->where('id', $row->id)
                                ->update(['correct_key' => $option['key']]);
                            break;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // Data backfill only; nothing to reverse.
    }
};
