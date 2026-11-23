<?php

namespace App\Services\PassimarkImport;

use App\Support\PsmkZip;

class QtiSourceAdapter implements SourceAdapter
{
    /**
     * Extract questions, metadata, and assets from an IMS QTI XML file or QTI package ZIP.
     */
    public function extract(string $path, array $options = []): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Source file does not exist: {$path}");
        }

        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException("Source file is empty.");
        }

        $xmlFiles = [];
        $assets = [];

        // Check if file is a ZIP package
        if (str_starts_with($binary, "PK\x03\x04")) {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    $content = $zip->getFromIndex($i);
                    if (str_ends_with(strtolower($name), '.xml')) {
                        // Skip imsmanifest unless needed
                        if (!str_contains(strtolower($name), 'imsmanifest.xml')) {
                            $xmlFiles[$name] = $content;
                        }
                    } elseif (preg_match('/\.(png|jpe?g|gif|svg|webp)$/i', $name)) {
                        $assets["assets/{$name}"] = $content;
                    }
                }
                $zip->close();
            }
        } else {
            // Single XML file
            $xmlFiles[pathinfo($path, PATHINFO_BASENAME)] = $binary;
        }

        if (empty($xmlFiles)) {
            throw new \InvalidArgumentException("No valid QTI assessment items found in package.");
        }

        $items = [];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $defaultDomain = $options['default_domain'] ?? 'Certification Domain';
        $title = $options['title'] ?? pathinfo($path, PATHINFO_FILENAME);

        foreach ($xmlFiles as $filename => $xmlContent) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            if ($xml === false) {
                continue;
            }

            // Correct responses map: responseIdentifier => array of correct values
            $correctMap = [];
            foreach ($xml->responseDeclaration as $rd) {
                $rId = (string) $rd['identifier'];
                $vals = [];
                if (isset($rd->correctResponse)) {
                    foreach ($rd->correctResponse->value as $v) {
                        $vals[] = trim((string) $v);
                    }
                }
                $correctMap[$rId] = $vals;
            }

            // Modal feedback / explanation
            $explanation = null;
            if (isset($xml->modalFeedback)) {
                $explanation = trim(strip_tags((string) $xml->modalFeedback));
            }

            // Iterate interactions in itemBody
            $body = $xml->itemBody ?? null;
            if (!$body) {
                continue;
            }

            $qTitle = (string) ($xml['title'] ?? pathinfo($filename, PATHINFO_FILENAME));

            // 1. Choice Interaction (Single or Multiple Choice)
            if (isset($body->choiceInteraction)) {
                $ci = $body->choiceInteraction;
                $maxChoices = (int) ($ci['maxChoices'] ?? 1);
                $respId = (string) ($ci['responseIdentifier'] ?? 'RESPONSE');
                $targetCorrect = $correctMap[$respId] ?? [];

                $prompt = trim(strip_tags((string) ($ci->prompt ?? '')));
                if ($prompt === '') {
                    $prompt = $qTitle;
                }

                $choices = [];
                $correctAnswers = [];
                $choiceIdx = 0;

                foreach ($ci->simpleChoice as $sc) {
                    $scId = (string) $sc['identifier'];
                    $scText = trim(strip_tags((string) $sc));
                    $isCorrect = in_array($scId, $targetCorrect, true);

                    $key = $letters[$choiceIdx] ?? (string) ($choiceIdx + 1);
                    $choices[] = [
                        'id' => $key,
                        'text' => $scText,
                        'is_correct' => $isCorrect,
                    ];
                    if ($isCorrect) {
                        $correctAnswers[] = $key;
                    }
                    $choiceIdx++;
                }

                $itemType = ($maxChoices === 1 && count($correctAnswers) <= 1) ? 'single_choice' : 'multiple_choice';

                $items[] = [
                    'source_index' => count($items) + 1,
                    'item_type' => $itemType,
                    'prompt' => $prompt,
                    'choices' => $choices,
                    'correct_answers' => $correctAnswers,
                    'explanation' => $explanation,
                    'reference' => "QTI: {$qTitle}",
                    'domain' => $defaultDomain,
                    'bloom' => 'Apply',
                    'difficulty' => 0.0,
                    'discrimination' => 1.0,
                    'guessing' => 0.25,
                    'interactive_payload' => null,
                    'exhibits' => null,
                ];
            }
            // 2. Order Interaction (Build List)
            elseif (isset($body->orderInteraction)) {
                $oi = $body->orderInteraction;
                $prompt = trim(strip_tags((string) ($oi->prompt ?? '')));
                if ($prompt === '') {
                    $prompt = $qTitle;
                }
                $elements = [];
                $targetOrder = $correctMap[(string) ($oi['responseIdentifier'] ?? 'RESPONSE')] ?? [];

                foreach ($oi->simpleChoice as $sc) {
                    $elements[] = [
                        'id' => (string) $sc['identifier'],
                        'text' => trim(strip_tags((string) $sc)),
                    ];
                }

                $items[] = [
                    'source_index' => count($items) + 1,
                    'item_type' => 'build_list',
                    'prompt' => $prompt,
                    'choices' => null,
                    'correct_answers' => $targetOrder,
                    'explanation' => $explanation,
                    'reference' => "QTI: {$qTitle}",
                    'domain' => $defaultDomain,
                    'bloom' => 'Analyze',
                    'difficulty' => 0.0,
                    'discrimination' => 1.0,
                    'guessing' => 0.1,
                    'interactive_payload' => [
                        'bank' => $elements,
                        'correct_sequence' => $targetOrder,
                    ],
                    'exhibits' => null,
                ];
            }
            // 3. Text Entry Interaction (Fill in the Blank)
            elseif (isset($body->textEntryInteraction)) {
                $tei = $body->textEntryInteraction;
                $respId = (string) ($tei['responseIdentifier'] ?? 'RESPONSE');
                $prompt = trim(strip_tags((string) $body));
                $targetAnswers = $correctMap[$respId] ?? [];

                $items[] = [
                    'source_index' => count($items) + 1,
                    'item_type' => 'fill_blank',
                    'prompt' => $prompt,
                    'choices' => null,
                    'correct_answers' => $targetAnswers,
                    'explanation' => $explanation,
                    'reference' => "QTI: {$qTitle}",
                    'domain' => $defaultDomain,
                    'bloom' => 'Recall',
                    'difficulty' => 0.0,
                    'discrimination' => 1.0,
                    'guessing' => 0.0,
                    'interactive_payload' => [
                        'acceptable_answers' => $targetAnswers,
                        'case_sensitive' => false,
                    ],
                    'exhibits' => null,
                ];
            }
        }

        return [
            'metadata' => [
                'title' => $title,
                'description' => $options['description'] ?? 'Imported from IMS QTI',
                'pass_score' => isset($options['pass_score']) ? (int) $options['pass_score'] : 70,
                'time_minutes' => isset($options['time_minutes']) ? (int) $options['time_minutes'] : 60,
                'cert_slug' => $options['cert_slug'] ?? 'imported-qti',
            ],
            'items' => $items,
            'assets' => $assets,
        ];
    }
}
