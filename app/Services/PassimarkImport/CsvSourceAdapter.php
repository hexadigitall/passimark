<?php

namespace App\Services\PassimarkImport;

use App\Services\PassimarkPackage\PackagingSpec;
use Illuminate\Support\Str;

class CsvSourceAdapter implements SourceAdapter
{
    /**
     * Extract questions from a CSV or TSV file.
     */
    public function extract(string $path, array $options = []): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Source file does not exist: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            throw new \InvalidArgumentException("Source file is empty.");
        }

        // Strip UTF-8 BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Detect delimiter: comma, semicolon, or tab
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ',';
        $commaCount = substr_count($firstLine, ',');
        $semiCount = substr_count($firstLine, ';');
        $tabCount = substr_count($firstLine, "\t");

        if ($tabCount > $commaCount && $tabCount > $semiCount) {
            $delimiter = "\t";
        } elseif ($semiCount > $commaCount) {
            $delimiter = ';';
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            throw new \InvalidArgumentException("No data rows found in CSV.");
        }

        $rows = [];
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        while (($data = fgetcsv($stream, 0, $delimiter)) !== false) {
            if ($data === [null] || empty(array_filter($data, fn ($v) => $v !== null && trim($v) !== ''))) {
                continue;
            }
            $rows[] = $data;
        }
        fclose($stream);

        if (empty($rows)) {
            throw new \InvalidArgumentException("CSV contains no valid rows.");
        }

        // Header mapping
        $rawHeaders = array_shift($rows);
        $headers = array_map(fn ($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace([' ', '-'], '_', (string) $h)))), $rawHeaders);

        $items = [];
        $defaultDomain = $options['default_domain'] ?? 'General Assessment';
        $title = $options['title'] ?? pathinfo($path, PATHINFO_FILENAME);

        foreach ($rows as $index => $row) {
            $record = [];
            foreach ($headers as $colIdx => $colName) {
                if ($colName !== '' && isset($row[$colIdx])) {
                    $record[$colName] = trim((string) $row[$colIdx]);
                }
            }

            $prompt = $record['prompt'] ?? $record['question'] ?? $record['stem'] ?? $record['content'] ?? null;
            if (!$prompt) {
                continue;
            }

            $rawType = strtolower($record['type'] ?? $record['item_type'] ?? 'single_choice');
            $type = in_array($rawType, PackagingSpec::ITEM_TYPES, true) ? $rawType : 'single_choice';

            // Extract choices
            $choices = [];
            $choiceColumns = ['choice_a', 'choice_b', 'choice_c', 'choice_d', 'choice_e', 'choice_f', 'choice_g', 'choice_h'];
            $altColumns = ['option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f', 'option_g', 'option_h'];
            $shortColumns = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];

            $rawCorrect = $record['correct_answer'] ?? $record['answer'] ?? $record['correct_key'] ?? $record['correct_choice_ids'] ?? $record['correct'] ?? '';
            $correctKeys = array_map(fn ($k) => strtoupper(trim($k)), preg_split('/[,|;]/', $rawCorrect));
            $correctKeys = array_filter($correctKeys, fn ($k) => $k !== '');

            // 1. Check individual choice columns
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
            $foundIndividual = false;

            foreach ($letters as $lIdx => $letter) {
                $cKey = strtolower($letter);
                $text = $record["choice_{$cKey}"] ?? $record["option_{$cKey}"] ?? $record[$cKey] ?? null;
                if ($text !== null && $text !== '') {
                    $foundIndividual = true;
                    $isCorrect = in_array($letter, $correctKeys, true) || in_array($text, $correctKeys, true);
                    $choices[] = [
                        'id' => $letter,
                        'text' => $text,
                        'is_correct' => $isCorrect,
                    ];
                }
            }

            // 2. If no individual columns, check delimited `choices` column
            if (!$foundIndividual && isset($record['choices'])) {
                $rawChoices = $record['choices'];
                $decoded = json_decode($rawChoices, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $cIdx => $cItem) {
                        $cId = is_array($cItem) ? ($cItem['id'] ?? $letters[$cIdx] ?? (string) ($cIdx + 1)) : ($letters[$cIdx] ?? (string) ($cIdx + 1));
                        $cText = is_array($cItem) ? ($cItem['text'] ?? '') : (string) $cItem;
                        $isCorrect = is_array($cItem) && isset($cItem['is_correct'])
                            ? (bool) $cItem['is_correct']
                            : in_array(strtoupper($cId), $correctKeys, true);
                        $choices[] = ['id' => $cId, 'text' => $cText, 'is_correct' => $isCorrect];
                    }
                } else {
                    $choiceParts = array_map('trim', explode('|', $rawChoices));
                    foreach ($choiceParts as $cIdx => $part) {
                        if ($part === '') continue;
                        $cId = $letters[$cIdx] ?? (string) ($cIdx + 1);
                        $isCorrect = in_array($cId, $correctKeys, true) || in_array($part, $correctKeys, true);
                        $choices[] = ['id' => $cId, 'text' => $part, 'is_correct' => $isCorrect];
                    }
                }
            }

            // If question has multiple correct answers, automatically classify as multiple_choice
            $correctCount = count(array_filter($choices, fn ($c) => $c['is_correct']));
            if ($correctCount > 1 && $type === 'single_choice') {
                $type = 'multiple_choice';
            }

            // Psychometrics
            $diff = isset($record['difficulty']) ? (float) $record['difficulty'] : (isset($record['b']) ? (float) $record['b'] : 0.0);
            $disc = isset($record['discrimination']) ? (float) $record['discrimination'] : (isset($record['a']) ? (float) $record['a'] : 1.0);
            $guess = isset($record['guessing']) ? (float) $record['guessing'] : (isset($record['c']) ? (float) $record['c'] : 0.25);

            $diff = max(PackagingSpec::IRT_B_DIFFICULTY_MIN, min(PackagingSpec::IRT_B_DIFFICULTY_MAX, $diff));
            $disc = max(PackagingSpec::IRT_A_DISCRIMINATION_MIN, min(PackagingSpec::IRT_A_DISCRIMINATION_MAX, $disc));
            $guess = max(PackagingSpec::IRT_C_GUESSING_MIN, min(PackagingSpec::IRT_C_GUESSING_MAX, $guess));

            $items[] = [
                'source_index' => count($items) + 1,
                'item_type' => $type,
                'prompt' => $prompt,
                'choices' => !empty($choices) ? $choices : null,
                'correct_answers' => !empty($correctKeys) ? array_values($correctKeys) : array_values(array_column(array_filter($choices, fn ($c) => $c['is_correct']), 'id')),
                'explanation' => $record['explanation'] ?? $record['rationale'] ?? null,
                'reference' => $record['reference'] ?? $record['ref'] ?? null,
                'domain' => $record['domain'] ?? $defaultDomain,
                'bloom' => $record['bloom'] ?? $record['bloom_level'] ?? 'Apply',
                'difficulty' => $diff,
                'discrimination' => $disc,
                'guessing' => $guess,
                'interactive_payload' => isset($record['payload']) ? json_decode($record['payload'], true) : null,
                'exhibits' => isset($record['exhibits']) ? json_decode($record['exhibits'], true) : null,
            ];
        }

        return [
            'metadata' => [
                'title' => $title,
                'description' => $options['description'] ?? 'Imported from CSV',
                'pass_score' => isset($options['pass_score']) ? (int) $options['pass_score'] : 70,
                'time_minutes' => isset($options['time_minutes']) ? (int) $options['time_minutes'] : 60,
                'cert_slug' => $options['cert_slug'] ?? 'imported-csv',
            ],
            'items' => $items,
            'assets' => [],
        ];
    }
}
