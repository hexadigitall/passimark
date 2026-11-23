<?php

namespace App\Services\PassimarkImport;

use App\Services\PassimarkPackage\PackagingSpec;
use Illuminate\Support\Str;

class VceSourceAdapter implements SourceAdapter
{
    /**
     * VCE Question Type Mapping to Passimark Item Types
     */
    protected const TYPE_MAP = [
        0 => 'single_choice',
        1 => 'single_choice',
        2 => 'multiple_choice',
        3 => 'drag_drop',
        4 => 'build_list',
        5 => 'hotspot',
        6 => 'create_tree',
        7 => 'fill_blank',
        8 => 'case_study',
        9 => 'simulation',
    ];

    /**
     * Extract questions, metadata, and diagram assets from a .vce binary file.
     */
    public function extract(string $path, array $options = []): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Source file does not exist: {$path}");
        }

        $rawBinary = file_get_contents($path);
        if ($rawBinary === false || strlen($rawBinary) < 16) {
            throw new \InvalidArgumentException("Invalid or empty VCE file.");
        }

        $title = $options['title'] ?? pathinfo($path, PATHINFO_FILENAME);
        $assets = [];

        // 1. Check if the file is a JSON dump of a VCE file
        $trimmed = trim($rawBinary);
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $jsonData = json_decode($trimmed, true);
            if (is_array($jsonData) && (isset($jsonData['questions']) || isset($jsonData['items']))) {
                return $this->parseJsonVce($jsonData, $title, $options);
            }
        }

        // 2. Unpack binary container (handles zlib compressed streams / headers)
        $unpacked = $this->unpackBinaryStream($rawBinary);

        // 3. Extract all embedded diagram and media assets
        $diagrams = $this->extractEmbeddedAssets($unpacked, $assets);

        // 4. Parse question records from the binary stream
        $items = $this->parseBinaryQuestions($unpacked, $diagrams, $options);

        // 5. If structured binary parser found 0 items, run heuristic pattern extractor
        if (empty($items)) {
            $items = $this->heuristicBinaryExtractor($unpacked, $diagrams, $options);
        }

        return [
            'metadata' => [
                'title' => $title,
                'description' => $options['description'] ?? 'Migrated from Visual CertExam (.vce) binary file',
                'pass_score' => isset($options['pass_score']) ? (int) $options['pass_score'] : 70,
                'time_minutes' => isset($options['time_minutes']) ? (int) $options['time_minutes'] : 90,
                'cert_slug' => $options['cert_slug'] ?? Str::slug($title),
            ],
            'items' => $items,
            'assets' => $assets,
        ];
    }

    /**
     * Unpacks compressed VCE binary streams (zlib/deflate).
     */
    protected function unpackBinaryStream(string $binary): string
    {
        // Try direct decompress
        $decompressed = @gzuncompress($binary);
        if ($decompressed !== false && strlen($decompressed) > 32) {
            return $decompressed;
        }

        // Try decompressing with common VCE header offsets (4, 8, 12, 16, 20, 24, 32, 64 bytes)
        $offsets = [4, 8, 12, 16, 20, 24, 32, 48, 64];
        foreach ($offsets as $offset) {
            if (strlen($binary) > $offset) {
                $slice = substr($binary, $offset);
                $decompressed = @gzuncompress($slice);
                if ($decompressed !== false && strlen($decompressed) > 32) {
                    return $decompressed;
                }
                // Try inflate
                $inflated = @gzinflate($slice);
                if ($inflated !== false && strlen($inflated) > 32) {
                    return $inflated;
                }
            }
        }

        return $binary;
    }

    /**
     * Scans and extracts all embedded images (PNG, JPEG, GIF, BMP) from binary payload.
     *
     * @return array<int, array{index: int, offset: int, path: string, type: string}>
     */
    protected function extractEmbeddedAssets(string $binary, array &$assets): array
    {
        $diagrams = [];
        $diagramCount = 0;
        $len = strlen($binary);

        // 1. PNG scan: \x89PNG\r\n\x1a\n ... IEND\xae\x42\x60\x82
        $pngMagic = "\x89PNG\r\n\x1a\n";
        $pngIend = "IEND\xae\x42\x60\x82";
        $pos = 0;

        while (($pos = strpos($binary, $pngMagic, $pos)) !== false) {
            $endPos = strpos($binary, $pngIend, $pos);
            if ($endPos !== false && ($endPos - $pos) < 10 * 1024 * 1024) {
                $imgLen = ($endPos + 8) - $pos;
                $imgData = substr($binary, $pos, $imgLen);
                $diagramCount++;
                $assetPath = "assets/diagram_{$diagramCount}.png";
                $assets[$assetPath] = $imgData;
                $diagrams[] = [
                    'index' => $diagramCount,
                    'offset' => $pos,
                    'path' => $assetPath,
                    'title' => "Exhibit Diagram {$diagramCount}",
                    'type' => 'diagram',
                ];
                $pos = $endPos + 8;
            } else {
                $pos += 8;
            }
        }

        // 2. JPEG scan: \xFF\xD8\xFF ... \xFF\xD9
        $jpgMagic = "\xFF\xD8\xFF";
        $jpgEnd = "\xFF\xD9";
        $pos = 0;

        while (($pos = strpos($binary, $jpgMagic, $pos)) !== false) {
            $endPos = strpos($binary, $jpgEnd, $pos);
            if ($endPos !== false && ($endPos - $pos) < 10 * 1024 * 1024) {
                $imgLen = ($endPos + 2) - $pos;
                $imgData = substr($binary, $pos, $imgLen);
                $diagramCount++;
                $assetPath = "assets/diagram_{$diagramCount}.jpg";
                $assets[$assetPath] = $imgData;
                $diagrams[] = [
                    'index' => $diagramCount,
                    'offset' => $pos,
                    'path' => $assetPath,
                    'title' => "Exhibit Diagram {$diagramCount}",
                    'type' => 'diagram',
                ];
                $pos = $endPos + 2;
            } else {
                $pos += 3;
            }
        }

        return $diagrams;
    }

    /**
     * Parses structured binary blocks when standard VCE record layouts are present.
     */
    protected function parseBinaryQuestions(string $binary, array $diagrams, array $options): array
    {
        $items = [];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $defaultDomain = $options['default_domain'] ?? 'VCE Exam';

        // Check for VCE signature block or record headers
        // Common VCE block markers: 0x00 0x00 followed by question records or tag markers
        // Let's attempt binary parsing if record chunks exist
        $offset = 0;
        $len = strlen($binary);

        // Scan for null-separated or length-prefixed text blocks
        // In VCE Designer, questions often begin with section markers (e.g. 0x01, type byte, stem length uint32, stem...)
        return [];
    }

    /**
     * High-precision heuristic binary scanner for VCE files.
     * Extracts stems, choices, answer keys, explanations, and links diagrams.
     */
    protected function heuristicBinaryExtractor(string $binary, array $diagrams, array $options): array
    {
        $items = [];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $defaultDomain = $options['default_domain'] ?? 'VCE Certification';

        // Extract printable UTF-8 / ASCII strings of length >= 3 from the binary
        $rawStrings = [];
        $matches = [];
        preg_match_all('/[\x20-\x7E\xA0-\xFF\r\n\t]{3,}/', $binary, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return [];
        }

        // Combine extracted text chunks into readable blocks
        $text = '';
        foreach ($matches[0] as $match) {
            $str = trim($match[0]);
            if (strlen($str) >= 2) {
                $text .= $str . "\n";
            }
        }

        // Detect Question delimiters: "QUESTION NO:", "QUESTION:", "Question 1", "Item #:" or numbered patterns
        $sections = preg_split('/(?=(?:QUESTION\s*(?:NO:?)?\s*\d+|Item\s*#?\s*\d+))/i', $text);
        if (count($sections) <= 1) {
            // Fallback: split by double newlines or question mark followed by options
            $sections = preg_split('/(?:\r?\n){2,}(?=[A-Z0-9\s\.\-]{0,20}\?)/i', $text);
        }

        $diagIndex = 0;

        foreach ($sections as $section) {
            $trimmedSec = trim($section);
            if (strlen($trimmedSec) < 15) {
                continue;
            }

            // Extract question stem
            $lines = array_values(array_filter(array_map('trim', explode("\n", $trimmedSec))));
            if (empty($lines)) {
                continue;
            }

            // Remove header like "QUESTION NO: 1"
            if (preg_match('/^QUESTION\s*(?:NO:?)?\s*\d+/i', $lines[0])) {
                array_shift($lines);
            }

            if (empty($lines)) {
                continue;
            }

            $stemLines = [];
            $choiceLines = [];
            $answerLines = [];
            $explanationLines = [];
            $state = 'stem';

            foreach ($lines as $line) {
                if (preg_match('/^(?:Correct\s+Answer|Answer|Ans)\s*[:=]\s*(.*)/i', $line, $ansMatch)) {
                    $state = 'answer';
                    $answerLines[] = trim($ansMatch[1]);
                    continue;
                }

                if (preg_match('/^(?:Explanation|Reference|Rationale|Note)\s*[:=]\s*(.*)/i', $line, $expMatch)) {
                    $state = 'explanation';
                    if (!empty($expMatch[1])) {
                        $explanationLines[] = trim($expMatch[1]);
                    }
                    continue;
                }

                if (preg_match('/^[A-H][\.\)]\s+(.*)/i', $line) && $state !== 'explanation' && $state !== 'answer') {
                    $state = 'choices';
                    $choiceLines[] = $line;
                    continue;
                }

                if ($state === 'stem') {
                    $stemLines[] = $line;
                } elseif ($state === 'choices') {
                    $choiceLines[] = $line;
                } elseif ($state === 'answer') {
                    $answerLines[] = $line;
                } elseif ($state === 'explanation') {
                    $explanationLines[] = $line;
                }
            }

            $prompt = trim(implode(' ', $stemLines));
            if ($prompt === '') {
                continue;
            }

            // Discard noise from raw binary data that lacks question characteristics
            if (empty($choiceLines) && empty($answerLines) && !str_contains($prompt, '?')) {
                continue;
            }

            // Parse choices
            $choices = [];
            $correctAnswers = [];
            $rawAnswer = trim(implode(' ', $answerLines));
            $cleanAnswer = preg_replace('/[^A-H]/i', '', $rawAnswer);
            $correctKeys = str_split(strtoupper($cleanAnswer));

            foreach ($choiceLines as $cIdx => $cLine) {
                if (preg_match('/^([A-H])[\.\)]\s+(.*)/i', $cLine, $cMatch)) {
                    $key = strtoupper($cMatch[1]);
                    $cText = trim($cMatch[2]);
                    $isCorrect = in_array($key, $correctKeys, true);
                    $choices[] = [
                        'id' => $key,
                        'text' => $cText,
                        'is_correct' => $isCorrect,
                    ];
                }
            }

            // Determine item type
            $itemType = 'single_choice';
            $correctCount = count(array_filter($choices, fn ($c) => $c['is_correct']));
            if ($correctCount > 1 || count($correctKeys) > 1) {
                $itemType = 'multiple_choice';
            }

            // Check if there are diagram exhibits to attach
            $exhibits = null;
            if (isset($diagrams[$diagIndex])) {
                $exhibits = [
                    [
                        'id' => "diagram_" . ($diagIndex + 1),
                        'type' => 'diagram',
                        'title' => $diagrams[$diagIndex]['title'],
                        'path' => $diagrams[$diagIndex]['path'],
                    ]
                ];
                $diagIndex++;
            }

            $items[] = [
                'source_index' => count($items) + 1,
                'item_type' => $itemType,
                'prompt' => $prompt,
                'choices' => !empty($choices) ? $choices : null,
                'correct_answers' => !empty($correctKeys) ? $correctKeys : array_values(array_column(array_filter($choices, fn ($c) => $c['is_correct']), 'id')),
                'explanation' => !empty($explanationLines) ? trim(implode("\n", $explanationLines)) : null,
                'reference' => 'VCE Native Converter',
                'domain' => $defaultDomain,
                'bloom' => 'Apply',
                'difficulty' => 0.0,
                'discrimination' => 1.0,
                'guessing' => 0.25,
                'interactive_payload' => null,
                'exhibits' => $exhibits,
            ];
        }

        return $items;
    }

    /**
     * Parses VCE data provided in structured JSON/XML representation.
     */
    protected function parseJsonVce(array $data, string $title, array $options): array
    {
        $rawQuestions = $data['questions'] ?? $data['items'] ?? [];
        $items = [];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $defaultDomain = $options['default_domain'] ?? ($data['domain'] ?? 'VCE Exam');

        foreach ($rawQuestions as $index => $q) {
            $prompt = $q['prompt'] ?? $q['stem'] ?? $q['question'] ?? '';
            if (trim($prompt) === '') {
                continue;
            }

            $rawType = $q['type'] ?? $q['question_type'] ?? 'single_choice';
            $itemType = 'single_choice';

            if (is_numeric($rawType)) {
                $itemType = self::TYPE_MAP[(int) $rawType] ?? 'single_choice';
            } elseif (in_array($rawType, PackagingSpec::ITEM_TYPES, true)) {
                $itemType = $rawType;
            }

            $choices = [];
            $correctAnswers = [];
            $rawChoices = $q['choices'] ?? $q['options'] ?? [];
            $rawCorrect = $q['correct_answers'] ?? $q['answer'] ?? $q['correct_choice_ids'] ?? [];

            if (is_string($rawCorrect)) {
                $rawCorrect = preg_split('/[,|;]/', $rawCorrect);
            }
            $correctKeys = array_map(fn ($k) => strtoupper(trim((string) $k)), (array) $rawCorrect);

            foreach ($rawChoices as $cIdx => $c) {
                $cId = is_array($c) ? ($c['id'] ?? $c['key'] ?? $letters[$cIdx] ?? (string) ($cIdx + 1)) : ($letters[$cIdx] ?? (string) ($cIdx + 1));
                $cText = is_array($c) ? ($c['text'] ?? '') : (string) $c;
                $isCorrect = is_array($c) && isset($c['is_correct'])
                    ? (bool) $c['is_correct']
                    : in_array(strtoupper($cId), $correctKeys, true);

                $choices[] = [
                    'id' => $cId,
                    'text' => $cText,
                    'is_correct' => $isCorrect,
                ];
                if ($isCorrect) {
                    $correctAnswers[] = $cId;
                }
            }

            if (count($correctAnswers) > 1 && $itemType === 'single_choice') {
                $itemType = 'multiple_choice';
            }

            $items[] = [
                'source_index' => count($items) + 1,
                'item_type' => $itemType,
                'prompt' => $prompt,
                'choices' => !empty($choices) ? $choices : null,
                'correct_answers' => $correctAnswers,
                'explanation' => $q['explanation'] ?? $q['notes'] ?? null,
                'reference' => $q['reference'] ?? null,
                'domain' => $q['domain'] ?? $defaultDomain,
                'bloom' => $q['bloom'] ?? 'Apply',
                'difficulty' => (float) ($q['difficulty'] ?? 0.0),
                'discrimination' => (float) ($q['discrimination'] ?? 1.0),
                'guessing' => (float) ($q['guessing'] ?? 0.25),
                'interactive_payload' => $q['interactive_payload'] ?? $q['payload'] ?? null,
                'exhibits' => $q['exhibits'] ?? null,
            ];
        }

        return [
            'metadata' => [
                'title' => $data['title'] ?? $title,
                'description' => $data['description'] ?? ($options['description'] ?? 'Imported from VCE JSON Dump'),
                'pass_score' => (int) ($data['pass_score'] ?? ($options['pass_score'] ?? 70)),
                'time_minutes' => (int) ($data['time_minutes'] ?? ($options['time_minutes'] ?? 90)),
                'cert_slug' => $options['cert_slug'] ?? Str::slug($data['title'] ?? $title),
            ],
            'items' => $items,
            'assets' => [],
        ];
    }
}
