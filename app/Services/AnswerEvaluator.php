<?php

namespace App\Services;

use App\Models\PassimarkQuestion;

/**
 * Polymorphic answer evaluation service supporting the Passimark Question Taxonomy:
 *  - single_choice
 *  - multiple_choice
 *  - drag_drop
 *  - hotspot
 *  - build_list
 *  - cloze
 *  - create_tree
 *  - fill_blank
 */
class AnswerEvaluator
{
    /**
     * Evaluate a candidate submission against a question.
     *
     * @param PassimarkQuestion $question
     * @param array{selected?: ?string, selections?: ?array, interaction_response?: mixed} $submission
     * @return array{is_correct: bool, score: float, selected_summary: string, raw_response: mixed, feedback: ?string}
     */
    public static function evaluate(PassimarkQuestion $question, array $submission): array
    {
        $type = $question->type ?? 'single_choice';

        return match ($type) {
            'multiple_choice' => self::evaluateMultipleChoice($question, $submission),
            'drag_drop' => self::evaluateDragDrop($question, $submission),
            'hotspot' => self::evaluateHotspot($question, $submission),
            'build_list' => self::evaluateBuildList($question, $submission),
            'fill_blank' => self::evaluateFillBlank($question, $submission),
            'cloze' => self::evaluateCloze($question, $submission),
            'create_tree' => self::evaluateCreateTree($question, $submission),
            default => self::evaluateSingleChoice($question, $submission),
        };
    }

    /**
     * Single choice evaluation (1-of-N).
     */
    public static function evaluateSingleChoice(PassimarkQuestion $question, array $submission): array
    {
        $selected = trim((string) ($submission['selected'] ?? ''));
        if ($selected === '' && !empty($submission['selections'])) {
            $selected = trim((string) $submission['selections'][0]);
        }

        $options = collect($question->options ?? []);
        $correctOption = $options->firstWhere('is_correct', true);
        $expectedKey = (string) ($question->correct_key ?? ($correctOption['key'] ?? ''));

        $isCorrect = $expectedKey !== '' && strcasecmp($selected, $expectedKey) === 0;

        return [
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? 1.0 : 0.0,
            'selected_summary' => $selected,
            'raw_response' => ['selected' => $selected],
            'feedback' => $isCorrect ? 'Correct' : 'Incorrect',
        ];
    }

    /**
     * Multiple choice evaluation (K-of-N).
     */
    public static function evaluateMultipleChoice(PassimarkQuestion $question, array $submission): array
    {
        $chosen = [];
        if (!empty($submission['selections']) && is_array($submission['selections'])) {
            $chosen = collect($submission['selections'])
                ->map(fn($v) => trim((string)$v))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        } elseif (!empty($submission['selected'])) {
            $chosen = collect(preg_split('/[,\s]+/', (string) $submission['selected']))
                ->map(fn($v) => trim((string)$v))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $options = collect($question->options ?? []);
        $correctKeys = $options
            ->filter(fn($o) => !empty($o['is_correct']))
            ->pluck('key')
            ->map(fn($k) => (string) $k)
            ->sort()
            ->values()
            ->all();

        // Exact match comparison
        $isCorrect = count($correctKeys) > 0 && $correctKeys === $chosen;
        $score = $isCorrect ? 1.0 : 0.0;

        // Check if partial scoring is explicitly requested in interactive_payload
        $payload = $question->interactive_payload ?? [];
        if (($payload['scoring_strategy'] ?? '') === 'partial' && count($correctKeys) > 0) {
            $correctHits = count(array_intersect($chosen, $correctKeys));
            $incorrectHits = count(array_diff($chosen, $correctKeys));
            $score = max(0.0, ($correctHits - $incorrectHits) / count($correctKeys));
            $score = round($score, 2);
        }

        return [
            'is_correct' => $isCorrect,
            'score' => $score,
            'selected_summary' => implode(',', $chosen),
            'raw_response' => ['selections' => $chosen],
            'feedback' => $isCorrect ? 'Correct' : 'Incorrect',
        ];
    }

    /**
     * Drag & Drop matching evaluation.
     * Expects submission['interaction_response'] to be a map of [target_slot_id => source_token_id].
     */
    public static function evaluateDragDrop(PassimarkQuestion $question, array $submission): array
    {
        $response = $submission['interaction_response'] ?? [];
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        }
        if (!is_array($response)) {
            $response = [];
        }

        $payload = $question->interactive_payload ?? [];
        $targets = $payload['targets'] ?? [];
        $totalTargets = count($targets);

        if ($totalTargets === 0) {
            return [
                'is_correct' => false,
                'score' => 0.0,
                'selected_summary' => json_encode($response),
                'raw_response' => $response,
                'feedback' => 'No target configuration found for drag-and-drop.',
            ];
        }

        $matchedCount = 0;
        $details = [];

        foreach ($targets as $target) {
            $targetId = (string) ($target['id'] ?? '');
            $expectedSourceId = (string) ($target['correct_source_id'] ?? '');
            $submittedSourceId = isset($response[$targetId]) ? (string) $response[$targetId] : '';

            $slotCorrect = ($expectedSourceId !== '' && $submittedSourceId === $expectedSourceId);
            if ($slotCorrect) {
                $matchedCount++;
            }
            $details[$targetId] = [
                'expected' => $expectedSourceId,
                'submitted' => $submittedSourceId,
                'correct' => $slotCorrect,
            ];
        }

        $isCorrect = ($matchedCount === $totalTargets);
        $score = round($matchedCount / $totalTargets, 4);

        return [
            'is_correct' => $isCorrect,
            'score' => $score,
            'selected_summary' => json_encode($response),
            'raw_response' => $response,
            'feedback' => $isCorrect ? "All {$totalTargets} matches correct." : "{$matchedCount} of {$totalTargets} matches correct.",
            'details' => $details,
        ];
    }

    /**
     * Point & Click (Hotspot) coordinate evaluation.
     * Evaluates candidate coordinate [x, y] against target boundaries.
     */
    public static function evaluateHotspot(PassimarkQuestion $question, array $submission): array
    {
        $response = $submission['interaction_response'] ?? [];
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        }

        $x = isset($response['x']) ? (float) $response['x'] : null;
        $y = isset($response['y']) ? (float) $response['y'] : null;

        if ($x === null || $y === null) {
            return [
                'is_correct' => false,
                'score' => 0.0,
                'selected_summary' => 'No coordinates provided',
                'raw_response' => $response,
                'feedback' => 'No coordinate selected.',
            ];
        }

        $payload = $question->interactive_payload ?? [];
        $shape = $payload['target_shape'] ?? 'circle';
        $bounds = $payload['target_bounds'] ?? [];
        $isCorrect = false;

        if ($shape === 'circle') {
            $cx = (float) ($bounds['center_x'] ?? 0);
            $cy = (float) ($bounds['center_y'] ?? 0);
            $r = (float) ($bounds['radius'] ?? 10);

            // Euclidean distance
            $distance = sqrt(pow($x - $cx, 2) + pow($y - $cy, 2));
            $isCorrect = ($distance <= $r);
        } elseif ($shape === 'rect') {
            $xMin = (float) ($bounds['x_min'] ?? ($bounds['x'] ?? 0));
            $yMin = (float) ($bounds['y_min'] ?? ($bounds['y'] ?? 0));
            $xMax = (float) ($bounds['x_max'] ?? ($xMin + ($bounds['width'] ?? 0)));
            $yMax = (float) ($bounds['y_max'] ?? ($yMin + ($bounds['height'] ?? 0)));

            $isCorrect = ($x >= $xMin && $x <= $xMax && $y >= $yMin && $y <= $yMax);
        } elseif ($shape === 'polygon') {
            $points = $bounds['points'] ?? [];
            $isCorrect = self::pointInPolygon($x, $y, $points);
        }

        return [
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? 1.0 : 0.0,
            'selected_summary' => sprintf('(%.1f, %.1f)', $x, $y),
            'raw_response' => ['x' => $x, 'y' => $y],
            'feedback' => $isCorrect ? 'Target hit' : 'Target missed',
        ];
    }

    /**
     * Build list and reorder sequence evaluation.
     */
    public static function evaluateBuildList(PassimarkQuestion $question, array $submission): array
    {
        $response = $submission['interaction_response'] ?? $submission['selections'] ?? [];
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            } else {
                $response = array_filter(array_map('trim', explode(',', $response)));
            }
        }

        $payload = $question->interactive_payload ?? [];
        $correctSequence = array_values($payload['correct_sequence'] ?? []);
        $submittedSequence = array_values((array) $response);

        $isCorrect = ($correctSequence === $submittedSequence);
        $score = $isCorrect ? 1.0 : 0.0;

        // Partial scoring if requested
        if (!$isCorrect && count($correctSequence) > 0 && ($payload['scoring_strategy'] ?? '') === 'kendall_tau_partial') {
            $matchedCount = 0;
            foreach ($correctSequence as $idx => $item) {
                if (isset($submittedSequence[$idx]) && $submittedSequence[$idx] === $item) {
                    $matchedCount++;
                }
            }
            $score = round($matchedCount / count($correctSequence), 4);
        }

        return [
            'is_correct' => $isCorrect,
            'score' => $score,
            'selected_summary' => implode(' -> ', $submittedSequence),
            'raw_response' => $submittedSequence,
            'feedback' => $isCorrect ? 'Sequence correct' : 'Sequence incorrect',
        ];
    }

    /**
     * Cloze dropdown evaluation.
     * Expects submission['interaction_response'] to be a map of [blank_id => selected_option].
     */
    public static function evaluateCloze(PassimarkQuestion $question, array $submission): array
    {
        $response = $submission['interaction_response'] ?? [];
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        }
        if (!is_array($response)) {
            $response = [];
        }

        $payload = $question->interactive_payload ?? [];
        $blanks = $payload['blanks'] ?? [];
        $totalBlanks = count($blanks);

        if ($totalBlanks === 0) {
            return [
                'is_correct' => false,
                'score' => 0.0,
                'selected_summary' => json_encode($response),
                'raw_response' => $response,
                'feedback' => 'No blank configuration found for cloze.',
            ];
        }

        $correctCount = 0;
        $details = [];

        foreach ($blanks as $blankId => $config) {
            $expected = trim((string) ($config['correct_answer'] ?? ''));
            $submitted = isset($response[$blankId]) ? trim((string) $response[$blankId]) : '';
            $isMatch = ($expected !== '' && strcasecmp($expected, $submitted) === 0);

            if ($isMatch) {
                $correctCount++;
            }

            $details[$blankId] = [
                'expected' => $expected,
                'submitted' => $submitted,
                'correct' => $isMatch,
            ];
        }

        $isCorrect = ($correctCount === $totalBlanks);
        $score = round($correctCount / $totalBlanks, 4);

        return [
            'is_correct' => $isCorrect,
            'score' => $score,
            'selected_summary' => json_encode($response),
            'raw_response' => $response,
            'feedback' => $isCorrect ? "All {$totalBlanks} blanks correct." : "{$correctCount} of {$totalBlanks} blanks correct.",
            'details' => $details,
        ];
    }

    /**
     * Create tree hierarchy evaluation.
     * Evaluates candidate submitted edges against correct_edges.
     * Supports:
     *   - List of edge objects: [["parent" => "root", "child" => "n1"], ...]
     *   - Key-value map: ["child_id" => "parent_id", ...]
     */
    public static function evaluateCreateTree(PassimarkQuestion $question, array $submission): array
    {
        $response = $submission['interaction_response'] ?? [];
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        }
        if (!is_array($response)) {
            $response = [];
        }

        $payload = $question->interactive_payload ?? [];
        $correctEdges = $payload['correct_edges'] ?? [];
        $totalExpectedEdges = count($correctEdges);

        if ($totalExpectedEdges === 0) {
            return [
                'is_correct' => false,
                'score' => 0.0,
                'selected_summary' => json_encode($response),
                'raw_response' => $response,
                'feedback' => 'No expected edge configuration found for create_tree.',
            ];
        }

        // Normalize expected edges into set of "parent->child" strings
        $expectedEdgeSet = [];
        foreach ($correctEdges as $edge) {
            $p = (string) ($edge['parent'] ?? '');
            $c = (string) ($edge['child'] ?? '');
            if ($p !== '' && $c !== '') {
                $expectedEdgeSet[] = "{$p}->{$c}";
            }
        }
        sort($expectedEdgeSet);

        // Normalize submitted edges
        $submittedEdgeSet = [];
        if (array_is_list($response)) {
            foreach ($response as $edge) {
                if (is_array($edge)) {
                    $p = (string) ($edge['parent'] ?? '');
                    $c = (string) ($edge['child'] ?? '');
                    if ($p !== '' && $c !== '') {
                        $submittedEdgeSet[] = "{$p}->{$c}";
                    }
                }
            }
        } else {
            // Map of child => parent
            foreach ($response as $child => $parent) {
                $p = (string) $parent;
                $c = (string) $child;
                if ($p !== '' && $c !== '') {
                    $submittedEdgeSet[] = "{$p}->{$c}";
                }
            }
        }
        $submittedEdgeSet = array_values(array_unique($submittedEdgeSet));
        sort($submittedEdgeSet);

        $matchedHits = count(array_intersect($submittedEdgeSet, $expectedEdgeSet));
        $extraHits = count(array_diff($submittedEdgeSet, $expectedEdgeSet));

        $isCorrect = ($matchedHits === $totalExpectedEdges && $extraHits === 0);
        $score = $totalExpectedEdges > 0 ? max(0.0, round(($matchedHits - $extraHits) / $totalExpectedEdges, 4)) : 0.0;

        return [
            'is_correct' => $isCorrect,
            'score' => $score,
            'selected_summary' => implode(', ', $submittedEdgeSet),
            'raw_response' => $response,
            'feedback' => $isCorrect ? "Tree structure correct ({$matchedHits}/{$totalExpectedEdges} edges)." : "Tree structure incorrect ({$matchedHits}/{$totalExpectedEdges} correct edges).",
        ];
    }

    /**
     * Fill in the blank text evaluation.
     */
    public static function evaluateFillBlank(PassimarkQuestion $question, array $submission): array
    {
        $input = trim((string) ($submission['selected'] ?? $submission['interaction_response']['text'] ?? ''));
        $payload = $question->interactive_payload ?? [];
        $accepted = (array) ($payload['accepted_answers'] ?? []);
        $caseSensitive = (bool) ($payload['case_sensitive'] ?? false);
        $regex = $payload['regex_pattern'] ?? null;

        $isCorrect = false;
        if ($regex && @preg_match($regex, $input)) {
            $isCorrect = true;
        } else {
            foreach ($accepted as $answer) {
                if ($caseSensitive ? $input === $answer : strcasecmp($input, (string) $answer) === 0) {
                    $isCorrect = true;
                    break;
                }
            }
        }

        return [
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? 1.0 : 0.0,
            'selected_summary' => $input,
            'raw_response' => ['text' => $input],
            'feedback' => $isCorrect ? 'Correct' : 'Incorrect',
        ];
    }

    /**
     * Ray-casting algorithm for testing if point (x, y) is inside a polygon.
     *
     * @param float $x
     * @param float $y
     * @param list<array{float, float}|array{x: float, y: float}> $polygon
     * @return bool
     */
    private static function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);
        if ($count < 3) return false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) ($polygon[$i][0] ?? $polygon[$i]['x'] ?? 0);
            $yi = (float) ($polygon[$i][1] ?? $polygon[$i]['y'] ?? 0);
            $xj = (float) ($polygon[$j][0] ?? $polygon[$j]['x'] ?? 0);
            $yj = (float) ($polygon[$j][1] ?? $polygon[$j]['y'] ?? 0);

            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi == 0 ? 1e-9 : ($yj - $yi)) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Sanitize interactive payload for candidate delivery (strip answer keys).
     */
    public static function sanitizeForCandidate(PassimarkQuestion $question, bool $revealAnswers = false): ?array
    {
        $type = $question->type ?? 'single_choice';
        $payload = $question->interactive_payload;

        if (!$payload || !is_array($payload)) {
            return null;
        }

        if ($revealAnswers) {
            return $payload;
        }

        // Deep copy to prevent mutating
        $sanitized = $payload;

        if ($type === 'drag_drop' && isset($sanitized['targets'])) {
            $sanitized['targets'] = array_map(function ($target) {
                unset($target['correct_source_id']);
                return $target;
            }, $sanitized['targets']);
        }

        if ($type === 'hotspot') {
            unset($sanitized['target_bounds']);
        }

        if ($type === 'build_list') {
            unset($sanitized['correct_sequence']);
        }

        if ($type === 'fill_blank') {
            unset($sanitized['accepted_answers'], $sanitized['regex_pattern']);
        }

        if ($type === 'cloze' && isset($sanitized['blanks']) && is_array($sanitized['blanks'])) {
            foreach ($sanitized['blanks'] as $k => $blank) {
                if (is_array($blank)) {
                    unset($sanitized['blanks'][$k]['correct_answer']);
                }
            }
        }

        if ($type === 'create_tree') {
            unset($sanitized['correct_edges']);
        }

        if ($type === 'case_study' && isset($sanitized['sub_items']) && is_array($sanitized['sub_items'])) {
            $sanitized['sub_items'] = array_map(function ($subItem) {
                if (isset($subItem['options']) && is_array($subItem['options'])) {
                    $subItem['options'] = array_map(function ($opt) {
                        unset($opt['is_correct']);
                        return $opt;
                    }, $subItem['options']);
                    unset($subItem['correct_key'], $subItem['correct_keys']);
                }
                if (isset($subItem['interactive_payload']) && is_array($subItem['interactive_payload'])) {
                    // recursively strip answers from sub-items if present
                    $dummyQ = new PassimarkQuestion([
                        'type' => $subItem['type'] ?? 'single_choice',
                        'interactive_payload' => $subItem['interactive_payload'],
                    ]);
                    $subItem['interactive_payload'] = self::sanitizeForCandidate($dummyQ, false);
                }
                return $subItem;
            }, $sanitized['sub_items']);
        }

        return $sanitized;
    }
}
