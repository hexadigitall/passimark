<?php

namespace App\Http\Controllers;

use App\Models\PassimarkQuestion;
use App\Services\AiTutoring\ExplanationGenerator;
use App\Services\AnswerEvaluator;
use App\Services\SpacedRepetition\LeitnerEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiTutorController extends Controller
{
    public function __construct(
        protected LeitnerEngine $leitnerEngine,
        protected ExplanationGenerator $explanationGenerator
    ) {}

    /**
     * Fetch candidate's customized "Daily Drill" questions sanitized against answer leakage.
     */
    public function drill(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(50, max(5, $request->integer('limit', 15)));

        $questions = $this->leitnerEngine->generateDailyDrill($user->id, $limit);

        // Sanitize questions so correct answers are not leaked to the candidate
        $sanitized = $questions->map(function ($q) {
            $options = collect($q->options ?? []);
            return [
                'id' => $q->id,
                'type' => $q->type ?? 'single_choice',
                'content' => $q->content,
                'domain' => $q->domain,
                'options' => $options->map(fn ($opt) => [
                    'key' => $opt['key'],
                    'text' => $opt['text'],
                ])->values()->all(),
                'interactive_payload' => AnswerEvaluator::sanitizeForCandidate($q, false),
                'exhibits' => $q->exhibits ?? [],
            ];
        });

        return response()->json([
            'count' => $sanitized->count(),
            'questions' => $sanitized,
        ]);
    }

    /**
     * Spaced Repetition mastery statistics across the 5 Leitner boxes.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->leitnerEngine->getStats($user->id);

        return response()->json($stats);
    }

    /**
     * Generate an in-depth AI distractor explanation for a chosen incorrect choice in Practice mode.
     */
    public function explain(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_id' => 'required|integer|exists:passimark_questions,id',
            'chosen_key' => 'required|string',
        ]);

        $question = PassimarkQuestion::findOrFail($data['question_id']);
        $analysis = $this->explanationGenerator->explainDistractor($question, $data['chosen_key']);

        return response()->json($analysis);
    }
}
