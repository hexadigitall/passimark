<?php

namespace App\Services\SpacedRepetition;

use App\Models\PassimarkQuestion;
use App\Models\PassimarkSpacedRepetition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeitnerEngine
{
    /**
     * Review interval schedule by Leitner Box level (in hours).
     * Box 1: 12 hours
     * Box 2: 72 hours (3 days)
     * Box 3: 168 hours (7 days)
     * Box 4: 336 hours (14 days)
     * Box 5: 720 hours (30 days - Mastered)
     */
    public const BOX_INTERVALS = [
        1 => 12,
        2 => 72,
        3 => 168,
        4 => 336,
        5 => 720,
    ];

    /**
     * Record a candidate's answer attempt and update Leitner 5-box positioning.
     */
    public function recordAttempt(int $userId, int $questionId, bool $isCorrect): PassimarkSpacedRepetition
    {
        $now = Carbon::now();

        $record = PassimarkSpacedRepetition::firstOrNew(
            ['user_id' => $userId, 'question_id' => $questionId],
            [
                'box' => 1,
                'consecutive_correct' => 0,
                'review_count' => 0,
            ]
        );

        $currentBox = $record->exists ? $record->box : 1;

        if ($isCorrect) {
            $newBox = min(5, $currentBox + 1);
            $consecutive = $record->consecutive_correct + 1;
        } else {
            // Regression on failure: drops back to Box 1 for immediate reinforcement
            $newBox = 1;
            $consecutive = 0;
        }

        $intervalHours = self::BOX_INTERVALS[$newBox] ?? 24;
        $nextReviewAt = (clone $now)->addHours($intervalHours);

        $record->box = $newBox;
        $record->consecutive_correct = $consecutive;
        $record->review_count = ($record->review_count ?? 0) + 1;
        $record->last_reviewed_at = $now;
        $record->next_review_at = $nextReviewAt;
        $record->save();

        return $record;
    }

    /**
     * Generate a personalized "Daily Drill" targeting items due for review
     * prioritized by box level and lowest-ability domains.
     */
    public function generateDailyDrill(int $userId, int $limit = 20): Collection
    {
        $now = Carbon::now();

        // 1. Fetch questions that are due for spaced repetition review
        $dueRepetitions = PassimarkSpacedRepetition::with('question')
            ->where('user_id', $userId)
            ->where('next_review_at', '<=', $now)
            ->orderBy('box', 'asc') // Lower boxes (struggled items) first
            ->orderBy('next_review_at', 'asc')
            ->limit($limit)
            ->get();

        $questions = $dueRepetitions->pluck('question')->filter()->values();

        // 2. If fewer than limit, backfill with previously missed questions or unreviewed questions
        if ($questions->count() < $limit) {
            $needed = $limit - $questions->count();
            $alreadyIncludedIds = $questions->pluck('id')->all();

            $backfill = PassimarkQuestion::whereNotIn('id', $alreadyIncludedIds)
                ->whereHas('session', fn ($q) => $q->where('is_active', true))
                ->inRandomOrder()
                ->limit($needed)
                ->get();

            $questions = $questions->concat($backfill);
        }

        return $questions;
    }

    /**
     * Compute candidate's Spaced Repetition mastery distribution across the 5 Leitner boxes.
     */
    public function getStats(int $userId): array
    {
        $now = Carbon::now();

        $counts = PassimarkSpacedRepetition::where('user_id', $userId)
            ->selectRaw('box, count(*) as total')
            ->groupBy('box')
            ->pluck('total', 'box')
            ->all();

        $dueCount = PassimarkSpacedRepetition::where('user_id', $userId)
            ->where('next_review_at', '<=', $now)
            ->count();

        return [
            'box_1' => (int) ($counts[1] ?? 0),
            'box_2' => (int) ($counts[2] ?? 0),
            'box_3' => (int) ($counts[3] ?? 0),
            'box_4' => (int) ($counts[4] ?? 0),
            'box_5' => (int) ($counts[5] ?? 0), // Mastered
            'total_tracked' => array_sum($counts),
            'mastered_count' => (int) ($counts[5] ?? 0),
            'due_for_review' => $dueCount,
        ];
    }
}
