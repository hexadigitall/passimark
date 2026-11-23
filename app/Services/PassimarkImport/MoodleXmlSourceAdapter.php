<?php

namespace App\Services\PassimarkImport;

class MoodleXmlSourceAdapter implements SourceAdapter
{
    /**
     * Extract questions, metadata, and embedded images from a Moodle XML file.
     */
    public function extract(string $path, array $options = []): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Source file does not exist: {$path}");
        }

        $xmlString = file_get_contents($path);
        if ($xmlString === false || trim($xmlString) === '') {
            throw new \InvalidArgumentException("Source file is empty.");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = !empty($errors) ? $errors[0]->message : 'Invalid XML';
            throw new \InvalidArgumentException("Failed to parse Moodle XML: {$msg}");
        }

        $items = [];
        $assets = [];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $defaultDomain = $options['default_domain'] ?? 'Certification Domain';
        $title = $options['title'] ?? pathinfo($path, PATHINFO_FILENAME);

        foreach ($xml->question as $qNode) {
            $qType = (string) $qNode['type'];
            if ($qType === 'category') {
                continue;
            }

            $name = trim((string) ($qNode->name->text ?? ''));
            $stemHtml = trim((string) ($qNode->questiontext->text ?? ''));
            $stem = trim(strip_tags($stemHtml));

            if ($stem === '' && $name !== '') {
                $stem = $name;
            }
            if ($stem === '') {
                continue;
            }

            // Extract general feedback
            $explanation = trim((string) ($qNode->generalfeedback->text ?? ''));
            if ($explanation !== '') {
                $explanation = trim(strip_tags($explanation));
            } else {
                $explanation = null;
            }

            // Check for embedded files/images in question text
            $exhibits = [];
            if (isset($qNode->questiontext->file)) {
                foreach ($qNode->questiontext->file as $fileNode) {
                    $filename = (string) $fileNode['name'];
                    $encoding = (string) $fileNode['encoding'];
                    $content = (string) $fileNode;

                    if ($encoding === 'base64' && $filename !== '') {
                        $binary = base64_decode($content);
                        $assetPath = "assets/{$filename}";
                        $assets[$assetPath] = $binary;
                        $exhibits[] = [
                            'id' => pathinfo($filename, PATHINFO_FILENAME),
                            'type' => 'diagram',
                            'title' => $filename,
                            'path' => $assetPath,
                        ];
                    }
                }
            }

            $itemType = 'single_choice';
            $choices = [];
            $correctAnswers = [];
            $payload = null;

            if ($qType === 'multichoice') {
                $isSingle = ((string) ($qNode->single ?? 'true')) === 'true' || ((string) ($qNode->single ?? '1')) === '1';
                $choiceIdx = 0;

                foreach ($qNode->answer as $ansNode) {
                    $fraction = (float) ($ansNode['fraction'] ?? 0);
                    $ansText = trim(strip_tags((string) ($ansNode->text ?? '')));
                    if ($ansText === '') {
                        continue;
                    }

                    $isCorrect = $fraction > 0;
                    $cId = $letters[$choiceIdx] ?? (string) ($choiceIdx + 1);

                    $choices[] = [
                        'id' => $cId,
                        'text' => $ansText,
                        'is_correct' => $isCorrect,
                    ];

                    if ($isCorrect) {
                        $correctAnswers[] = $cId;
                    }

                    $ansFeedback = trim(strip_tags((string) ($ansNode->feedback->text ?? '')));
                    if ($ansFeedback !== '' && $explanation === null) {
                        $explanation = $ansFeedback;
                    }

                    $choiceIdx++;
                }

                $itemType = ($isSingle && count($correctAnswers) <= 1) ? 'single_choice' : 'multiple_choice';
            } elseif ($qType === 'truefalse') {
                $itemType = 'single_choice';
                $trueCorrect = false;
                foreach ($qNode->answer as $ansNode) {
                    $fraction = (float) ($ansNode['fraction'] ?? 0);
                    $ansText = strtolower(trim((string) ($ansNode->text ?? '')));
                    if (($ansText === 'true' || $ansText === '1') && $fraction > 0) {
                        $trueCorrect = true;
                    }
                }
                $choices = [
                    ['id' => 'A', 'text' => 'True', 'is_correct' => $trueCorrect],
                    ['id' => 'B', 'text' => 'False', 'is_correct' => !$trueCorrect],
                ];
                $correctAnswers = [$trueCorrect ? 'A' : 'B'];
            } elseif ($qType === 'shortanswer') {
                $itemType = 'fill_blank';
                $acceptable = [];
                foreach ($qNode->answer as $ansNode) {
                    $fraction = (float) ($ansNode['fraction'] ?? 0);
                    if ($fraction > 0) {
                        $acceptable[] = trim((string) ($ansNode->text ?? ''));
                    }
                }
                $correctAnswers = $acceptable;
                $payload = [
                    'acceptable_answers' => $acceptable,
                    'case_sensitive' => false,
                ];
            } elseif ($qType === 'matching') {
                $itemType = 'drag_drop';
                $slots = [];
                $draggables = [];
                $mapping = [];
                $matchIdx = 1;

                foreach ($qNode->subquestion as $subNode) {
                    $subText = trim(strip_tags((string) ($subNode->text ?? '')));
                    $ansText = trim(strip_tags((string) ($subNode->answer->text ?? '')));

                    if ($subText === '' || $ansText === '') {
                        continue;
                    }

                    $slotId = "slot_{$matchIdx}";
                    $dragId = "card_{$matchIdx}";

                    $slots[] = ['id' => $slotId, 'label' => $subText];
                    $draggables[] = ['id' => $dragId, 'text' => $ansText];
                    $mapping[$slotId] = $dragId;
                    $matchIdx++;
                }

                $payload = [
                    'slots' => $slots,
                    'draggables' => $draggables,
                    'correct_mapping' => $mapping,
                ];
            }

            $items[] = [
                'source_index' => count($items) + 1,
                'item_type' => $itemType,
                'prompt' => $stem,
                'choices' => !empty($choices) ? $choices : null,
                'correct_answers' => $correctAnswers,
                'explanation' => $explanation,
                'reference' => $name !== '' ? "Moodle: {$name}" : null,
                'domain' => $defaultDomain,
                'bloom' => 'Apply',
                'difficulty' => 0.0,
                'discrimination' => 1.0,
                'guessing' => 0.25,
                'interactive_payload' => $payload,
                'exhibits' => !empty($exhibits) ? $exhibits : null,
            ];
        }

        return [
            'metadata' => [
                'title' => $title,
                'description' => $options['description'] ?? 'Imported from Moodle XML',
                'pass_score' => isset($options['pass_score']) ? (int) $options['pass_score'] : 70,
                'time_minutes' => isset($options['time_minutes']) ? (int) $options['time_minutes'] : 60,
                'cert_slug' => $options['cert_slug'] ?? 'imported-moodle',
            ],
            'items' => $items,
            'assets' => $assets,
        ];
    }
}
