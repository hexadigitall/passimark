<?php

namespace App\Services\PassimarkImport;

class GiftSourceAdapter implements SourceAdapter
{
    /**
     * Extract questions from a Moodle GIFT format file.
     */
    public function extract(string $path, array $options = []): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Source file does not exist: {$path}");
        }

        $rawContent = file_get_contents($path);
        if ($rawContent === false || trim($rawContent) === '') {
            throw new \InvalidArgumentException("Source file is empty.");
        }

        // Strip UTF-8 BOM
        if (str_starts_with($rawContent, "\xEF\xBB\xBF")) {
            $rawContent = substr($rawContent, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', $rawContent);
        $cleanLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Skip pure comment lines
            if (str_starts_with($trimmed, '//')) {
                continue;
            }
            $cleanLines[] = $line;
        }

        $cleanText = implode("\n", $cleanLines);

        // GIFT questions are separated by blank lines or delineated by `{...}`
        // Match patterns: optional ::Title:: Question text { answer block } optional trailing text
        $items = [];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $defaultDomain = $options['default_domain'] ?? 'Certification Domain';
        $title = $options['title'] ?? pathinfo($path, PATHINFO_FILENAME);

        // Regex to match question blocks
        preg_match_all('/(?:(?:::([^:]+)::)?\s*([^{]+)\{([^}]*)\}([^\n\r]*))/s', $cleanText, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $qTitle = trim($match[1] ?? '');
            $qStem = trim($match[2] ?? '');
            $answerBlock = trim($match[3] ?? '');
            $trailingText = trim($match[4] ?? '');

            if ($trailingText !== '') {
                $qStem .= ' _____ ' . $trailingText;
            }

            if ($qStem === '' && $qTitle !== '') {
                $qStem = $qTitle;
            }

            if ($qStem === '') {
                continue;
            }

            // General feedback if present inside answer block `####feedback`
            $explanation = null;
            if (str_contains($answerBlock, '####')) {
                $parts = explode('####', $answerBlock, 2);
                $answerBlock = trim($parts[0]);
                $explanation = trim($parts[1]);
            }

            $itemType = 'single_choice';
            $choices = [];
            $correctAnswers = [];
            $payload = null;

            // Check if True/False
            $upperBlock = strtoupper($answerBlock);
            if ($upperBlock === 'T' || $upperBlock === 'TRUE' || $upperBlock === 'F' || $upperBlock === 'FALSE') {
                $isTrue = str_starts_with($upperBlock, 'T');
                $choices = [
                    ['id' => 'A', 'text' => 'True', 'is_correct' => $isTrue],
                    ['id' => 'B', 'text' => 'False', 'is_correct' => !$isTrue],
                ];
                $correctAnswers = [$isTrue ? 'A' : 'B'];
            }
            // Check if Matching (Drag & Drop) e.g. =sub1 -> match1 =sub2 -> match2
            elseif (str_contains($answerBlock, '->')) {
                $itemType = 'drag_drop';
                preg_match_all('/=(.*?)\s*->\s*(.*?)(?=\s*=[^>]|$)/s', $answerBlock, $pairMatches, PREG_SET_ORDER);
                $pairs = [];
                $draggables = [];
                $targets = [];
                foreach ($pairMatches as $pIdx => $pm) {
                    $source = trim($pm[1]);
                    $target = trim($pm[2]);
                    $pId = "slot_" . ($pIdx + 1);
                    $cId = "card_" . ($pIdx + 1);
                    $targets[] = ['id' => $pId, 'label' => $source];
                    $draggables[] = ['id' => $cId, 'text' => $target];
                    $pairs[$pId] = $cId;
                }
                $payload = [
                    'slots' => $targets,
                    'draggables' => $draggables,
                    'correct_mapping' => $pairs,
                ];
            }
            // Check if Short Answer / Fill in Blank (starts with = and no ~)
            elseif (!str_contains($answerBlock, '~') && str_starts_with($answerBlock, '=')) {
                $itemType = 'fill_blank';
                preg_match_all('/=\s*([^=#]+)/', $answerBlock, $ansMatches);
                $validAnswers = array_map('trim', $ansMatches[1]);
                $correctAnswers = array_values(array_filter($validAnswers));
                $payload = [
                    'acceptable_answers' => $correctAnswers,
                    'case_sensitive' => false,
                ];
            }
            // Standard Multiple Choice / Multi-Response
            else {
                // Parse options starting with = or ~
                preg_match_all('/([=~])(%[-0-9\.]+%)?([^=~#]*)(?:#([^=~]*))?/s', $answerBlock, $optMatches, PREG_SET_ORDER);

                $choiceIdx = 0;
                $hasMultiCorrect = false;

                foreach ($optMatches as $om) {
                    $marker = $om[1]; // = or ~
                    $weight = $om[2] ?? '';
                    $optText = trim($om[3]);
                    $feedback = trim($om[4] ?? '');

                    if ($optText === '' && $marker === '') {
                        continue;
                    }

                    $isCorrect = false;
                    if ($marker === '=') {
                        $isCorrect = true;
                    } elseif ($weight !== '') {
                        $pct = (float) trim($weight, '%');
                        if ($pct > 0) {
                            $isCorrect = true;
                            $hasMultiCorrect = true;
                        }
                    }

                    $cId = $letters[$choiceIdx] ?? (string) ($choiceIdx + 1);
                    $choices[] = [
                        'id' => $cId,
                        'text' => $optText,
                        'is_correct' => $isCorrect,
                    ];

                    if ($isCorrect) {
                        $correctAnswers[] = $cId;
                    }
                    if ($feedback !== '' && $explanation === null) {
                        $explanation = $feedback;
                    }
                    $choiceIdx++;
                }

                if (count($correctAnswers) > 1 || $hasMultiCorrect) {
                    $itemType = 'multiple_choice';
                }
            }

            $items[] = [
                'source_index' => count($items) + 1,
                'item_type' => $itemType,
                'prompt' => $qStem,
                'choices' => !empty($choices) ? $choices : null,
                'correct_answers' => $correctAnswers,
                'explanation' => $explanation,
                'reference' => $qTitle !== '' ? "GIFT: {$qTitle}" : null,
                'domain' => $defaultDomain,
                'bloom' => 'Understand',
                'difficulty' => 0.0,
                'discrimination' => 1.0,
                'guessing' => 0.25,
                'interactive_payload' => $payload,
                'exhibits' => null,
            ];
        }

        return [
            'metadata' => [
                'title' => $title,
                'description' => $options['description'] ?? 'Imported from Moodle GIFT format',
                'pass_score' => isset($options['pass_score']) ? (int) $options['pass_score'] : 70,
                'time_minutes' => isset($options['time_minutes']) ? (int) $options['time_minutes'] : 60,
                'cert_slug' => $options['cert_slug'] ?? 'imported-gift',
            ],
            'items' => $items,
            'assets' => [],
        ];
    }
}
