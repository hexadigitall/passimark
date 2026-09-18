<?php

namespace App\Services;

use App\Models\PassimarkAttempt;
use App\Models\PassimarkProgress;

class ReviewService
{
    /**
     * Build the post-assessment review payload for a finished attempt.
     *
     * Exposure rule: an item's correct answer and its explanation are exposed only when the
     * learner already answered that item correctly, every item in the attempt was answered
     * correctly, or an instructor has approved the session. Locked items still show the
     * question and the learner's own selection so a reattempt never leaks the answers.
     */
    public static function payload(PassimarkAttempt $attempt, int $userId): array
    {
        $answers = $attempt->answers()->with('question')->get();
        $total = $answers->count();
        $correct = $answers->where('is_correct', true)->count();
        $allCorrect = $total > 0 && $correct === $total;
        $progress = PassimarkProgress::where('user_id', $userId)->where('session_id', $attempt->session_id)->first();
        $approved = optional($progress)->status === PassimarkProgress::APPROVED;
        $unlocked = $approved || $allCorrect;

        return [
            'attempt' => $attempt->load('session', 'exam'),
            'answers' => $answers->map(fn ($answer) => [
                'id' => $answer->id,
                'is_correct' => (bool) $answer->is_correct,
                'selected_option' => $answer->selected_option,
                'exposed' => (bool) ($answer->is_correct || $unlocked),
                'question' => [
                    'id' => $answer->question->id,
                    'content' => $answer->question->content,
                    'options' => collect($answer->question->options)
                        ->map(fn ($option) => ['key' => $option['key'], 'text' => $option['text']])
                        ->values(),
                    'correct_key' => ($answer->is_correct || $unlocked)
                        ? ($answer->question->correct_key ?? collect($answer->question->options)->firstWhere('is_correct', true)['key'] ?? null)
                        : null,
                    'explanation' => ($answer->is_correct || $unlocked) ? $answer->question->explanation : null,
                ],
            ])->values(),
            'review_unlocked' => $unlocked,
            'progress_status' => optional($progress)->status ?? PassimarkProgress::LOCKED,
            'certificate' => CertificateIssuer::summary($progress),
            'history' => PassimarkAttempt::where('user_id', $userId)
                ->where('session_id', $attempt->session_id)
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->get(['id', 'mode', 'score', 'is_passed', 'finished_at']),
        ];
    }
}