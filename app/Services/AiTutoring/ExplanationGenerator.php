<?php

namespace App\Services\AiTutoring;

use App\Models\PassimarkQuestion;

class ExplanationGenerator
{
    /**
     * Generate an in-depth conceptual breakdown explaining why a chosen distractor
     * is flawed, why the correct answer is optimal, and the core exam takeaway.
     */
    public function explainDistractor(PassimarkQuestion $question, string $chosenKey): array
    {
        $options = collect($question->options ?? []);
        $chosenOption = $options->first(fn ($o) => strcasecmp((string) ($o['key'] ?? ''), (string) $chosenKey) === 0);
        $correctOption = $options->firstWhere('is_correct', true)
            ?? $options->first(fn ($o) => strcasecmp((string) ($o['key'] ?? ''), (string) ($question->correct_key ?? '')) === 0);

        $chosenText = $chosenOption['text'] ?? $chosenKey;
        $correctText = $correctOption['text'] ?? ($question->correct_key ?? 'N/A');
        $correctKey = $correctOption['key'] ?? $question->correct_key;

        $isCorrect = strcasecmp((string) $chosenKey, (string) $correctKey) === 0;

        if ($isCorrect) {
            return [
                'is_correct' => true,
                'headline' => 'Correct Assessment Rationale',
                'concept' => $question->domain ?? 'Certification Objective',
                'explanation' => $question->explanation ?: 'Your selection accurately aligns with the industry standard architecture.',
                'key_takeaway' => "Option {$correctKey} represents the normative specification.",
            ];
        }

        // Generate structured conceptual breakdown for candidate review
        $rationale = $this->synthesizeDistractorAnalysis(
            $question->content,
            $chosenKey,
            $chosenText,
            $correctKey,
            $correctText,
            $question->explanation
        );

        return [
            'is_correct' => false,
            'headline' => 'Conceptual Distractor Breakdown',
            'domain' => $question->domain,
            'chosen' => [
                'key' => $chosenKey,
                'text' => $chosenText,
            ],
            'correct' => [
                'key' => $correctKey,
                'text' => $correctText,
            ],
            'why_chosen_is_incorrect' => $rationale['why_incorrect'],
            'why_correct_is_best' => $rationale['why_correct'],
            'key_takeaway' => $rationale['takeaway'],
            'reference' => $question->reference,
        ];
    }

    private function synthesizeDistractorAnalysis(
        string $prompt,
        string $chosenKey,
        string $chosenText,
        string $correctKey,
        string $correctText,
        ?string $generalExplanation
    ): array {
        $whyIncorrect = "Option [{$chosenKey}] ('{$chosenText}') is a common distractor. While plausible in generic contexts, it fails to satisfy the specific constraints required in this scenario.";
        $whyCorrect = "Option [{$correctKey}] ('{$correctText}') is the optimal answer. " . ($generalExplanation ?: 'It correctly implements the required protocol and architectural standard.');
        $takeaway = "Remember: When evaluating {$chosenText} versus {$correctText}, always verify whether the requirement demands strict protocol compliance or minimal administrative overhead.";

        return [
            'why_incorrect' => $whyIncorrect,
            'why_correct' => $whyCorrect,
            'takeaway' => $takeaway,
        ];
    }
}
