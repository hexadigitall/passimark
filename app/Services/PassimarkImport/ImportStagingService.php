<?php

namespace App\Services\PassimarkImport;

use App\Models\PassimarkImportBatch;
use App\Models\PassimarkImportItem;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\User;
use App\Services\PassimarkPackage\PackageValidator;
use App\Services\PassimarkPackage\PackagingSpec;
use App\Support\PsmkZip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportStagingService
{
    /**
     * Map source type to adapter class.
     */
    protected array $adapterMap = [
        'vce' => VceSourceAdapter::class,
        'csv' => CsvSourceAdapter::class,
        'tsv' => CsvSourceAdapter::class,
        'gift' => GiftSourceAdapter::class,
        'moodle_xml' => MoodleXmlSourceAdapter::class,
        'xml' => MoodleXmlSourceAdapter::class,
        'qti' => QtiSourceAdapter::class,
    ];

    /**
     * Staging workflow: Upload/Path -> Parse -> Normalize -> Validate -> Persist to Staging DB.
     */
    public function createBatch(string|UploadedFile $file, ?string $sourceType = null, ?User $user = null, array $options = []): PassimarkImportBatch
    {
        $filePath = '';
        $originalFilename = '';

        if ($file instanceof UploadedFile) {
            $originalFilename = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension());
            $tempPath = $file->getRealPath();
            $targetDir = storage_path('app/imports/temp');
            File::ensureDirectoryExists($targetDir);
            $filePath = $targetDir . '/' . Str::uuid() . '.' . $ext;
            copy($tempPath, $filePath);
        } else {
            $filePath = $file;
            $originalFilename = pathinfo($file, PATHINFO_BASENAME);
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }

        if (!is_file($filePath)) {
            throw new \InvalidArgumentException("Source file not found: {$filePath}");
        }

        $checksum = hash_file('sha256', $filePath);

        // Detect source type if not specified
        if (!$sourceType || $sourceType === 'auto') {
            $sourceType = match ($ext) {
                'vce' => 'vce',
                'csv', 'tsv' => 'csv',
                'gift', 'txt' => 'gift',
                'xml' => 'moodle_xml',
                'qti', 'zip' => 'qti',
                default => 'csv',
            };
        }

        $adapterClass = $this->adapterMap[strtolower($sourceType)] ?? CsvSourceAdapter::class;
        /** @var SourceAdapter $adapter */
        $adapter = new $adapterClass();

        // 1. Extract from source adapter
        $extraction = $adapter->extract($filePath, array_merge($options, [
            'title' => $options['title'] ?? pathinfo($originalFilename, PATHINFO_FILENAME),
        ]));

        $metadata = $extraction['metadata'] ?? [];
        $rawItems = $extraction['items'] ?? [];
        $assets = $extraction['assets'] ?? [];

        // 2. Persist batch
        $batch = PassimarkImportBatch::create([
            'user_id' => $user?->id,
            'source_type' => $sourceType,
            'original_filename' => $originalFilename,
            'file_path' => $filePath,
            'checksum' => $checksum,
            'status' => 'staged',
            'total_items' => count($rawItems),
            'valid_items' => 0,
            'error_items' => 0,
            'metadata' => array_merge($metadata, [
                'has_assets' => !empty($assets),
                'asset_count' => count($assets),
            ]),
            'error_log' => null,
        ]);

        // 3. Store assets in batch storage if any
        if (!empty($assets)) {
            $assetDir = storage_path("app/imports/{$batch->id}/assets");
            File::ensureDirectoryExists($assetDir);
            foreach ($assets as $relPath => $binary) {
                $dest = storage_path("app/imports/{$batch->id}/" . $relPath);
                File::ensureDirectoryExists(dirname($dest));
                file_put_contents($dest, $binary);
            }
        }

        // 4. Normalize and validate each item
        $validCount = 0;
        $errorCount = 0;

        foreach ($rawItems as $idx => $rawItem) {
            $normalized = $this->normalizeAndValidateItem($rawItem, $idx + 1);
            $normalized['batch_id'] = $batch->id;

            PassimarkImportItem::create($normalized);

            if ($normalized['status'] === 'error') {
                $errorCount++;
            } else {
                $validCount++;
            }
        }

        $batch->update([
            'valid_items' => $validCount,
            'error_items' => $errorCount,
        ]);

        return $batch->fresh(['items']);
    }

    /**
     * Normalizes and validates an item against Passimark psychometric and schema standards.
     */
    protected function normalizeAndValidateItem(array $item, int $sourceIndex): array
    {
        $errors = [];
        $prompt = trim((string) ($item['prompt'] ?? ''));
        if ($prompt === '') {
            $errors[] = 'Question prompt cannot be empty.';
        }

        $rawType = $item['item_type'] ?? 'single_choice';
        $type = in_array($rawType, PackagingSpec::ITEM_TYPES, true) ? $rawType : 'single_choice';

        $choices = is_array($item['choices'] ?? null) ? $item['choices'] : [];
        $correctAnswers = is_array($item['correct_answers'] ?? null) ? $item['correct_answers'] : [];

        // Validation for choice-based items
        if ($type === 'single_choice' || $type === 'multiple_choice') {
            if (empty($choices)) {
                $errors[] = 'Choice-based question must have options.';
            } else {
                // Ensure choices have valid structure
                $cleanedChoices = [];
                foreach ($choices as $cIdx => $c) {
                    $cId = (string) ($c['id'] ?? (string) ($cIdx + 1));
                    $cText = trim((string) ($c['text'] ?? ''));
                    $isCorrect = (bool) ($c['is_correct'] ?? false);
                    if ($cText === '') {
                        $errors[] = "Choice {$cId} text is empty.";
                    }
                    $cleanedChoices[] = [
                        'id' => $cId,
                        'text' => $cText,
                        'is_correct' => $isCorrect,
                    ];
                }
                $choices = $cleanedChoices;

                $correctCount = count(array_filter($choices, fn ($c) => $c['is_correct']));
                if ($correctCount === 0 && !empty($correctAnswers)) {
                    // Try to re-flag is_correct based on correct_answers
                    foreach ($choices as &$c) {
                        if (in_array($c['id'], $correctAnswers, true) || in_array($c['text'], $correctAnswers, true)) {
                            $c['is_correct'] = true;
                        }
                    }
                    unset($c);
                    $correctCount = count(array_filter($choices, fn ($c) => $c['is_correct']));
                }

                if ($correctCount === 0) {
                    $errors[] = 'Question must have at least one marked correct answer.';
                } elseif ($type === 'single_choice' && $correctCount > 1) {
                    // Automatically upgrade to multiple_choice
                    $type = 'multiple_choice';
                }
            }
        }

        // Psychometric clamping
        $diff = (float) ($item['difficulty'] ?? 0.0);
        $disc = (float) ($item['discrimination'] ?? 1.0);
        $guess = (float) ($item['guessing'] ?? 0.25);

        $diff = max(PackagingSpec::IRT_B_DIFFICULTY_MIN, min(PackagingSpec::IRT_B_DIFFICULTY_MAX, $diff));
        $disc = max(PackagingSpec::IRT_A_DISCRIMINATION_MIN, min(PackagingSpec::IRT_A_DISCRIMINATION_MAX, $disc));
        $guess = max(PackagingSpec::IRT_C_GUESSING_MIN, min(PackagingSpec::IRT_C_GUESSING_MAX, $guess));

        $status = empty($errors) ? 'valid' : 'error';

        return [
            'source_index' => $sourceIndex,
            'item_type' => $type,
            'prompt' => $prompt,
            'choices' => !empty($choices) ? $choices : null,
            'correct_answers' => !empty($correctAnswers) ? $correctAnswers : array_values(array_column(array_filter($choices, fn ($c) => $c['is_correct']), 'id')),
            'explanation' => $item['explanation'] ?? null,
            'reference' => $item['reference'] ?? null,
            'domain' => $item['domain'] ?? 'Certification Domain',
            'bloom' => $item['bloom'] ?? 'Apply',
            'difficulty' => $diff,
            'discrimination' => $disc,
            'guessing' => $guess,
            'interactive_payload' => $item['interactive_payload'] ?? null,
            'exhibits' => $item['exhibits'] ?? null,
            'status' => $status,
            'validation_errors' => !empty($errors) ? $errors : null,
        ];
    }

    /**
     * Converts approved staged items into an industry-standard .psmk package.
     */
    public function exportToPsmk(PassimarkImportBatch $batch, array $options = []): array
    {
        $items = $batch->items()
            ->whereIn('status', ['valid', 'approved'])
            ->orderBy('source_index')
            ->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException("No approved or valid items found in this batch to export.");
        }

        $packageId = (string) Str::uuid();
        $title = $options['title'] ?? $batch->metadata['title'] ?? $batch->original_filename;
        $certSlug = $options['cert_slug'] ?? $batch->metadata['cert_slug'] ?? Str::slug($title);
        $timeMinutes = (int) ($options['time_minutes'] ?? $batch->metadata['time_minutes'] ?? 90);
        $passScore = (int) ($options['pass_score'] ?? $batch->metadata['pass_score'] ?? 70);

        $now = now()->toRfc3339String();

        $manifest = [
            'format' => PackagingSpec::FORMAT,
            'format_version' => PackagingSpec::FORMAT_VERSION,
            'package_id' => $packageId,
            'content_type' => 'exam',
            'title' => Str::limit($title, 250, ''),
            'created_at' => $now,
            'updated_at' => $now,
            'producer' => [
                'name' => 'Passimark Universal Importer',
                'version' => PackagingSpec::FORMAT_VERSION,
            ],
        ];

        $examUuid = (string) Str::uuid();
        $questionsPayload = [];

        foreach ($items as $item) {
            $optionsList = $item->choices ?? [];
            $correctChoiceIds = array_values(array_column(array_filter($optionsList, fn ($c) => $c['is_correct'] ?? false), 'id'));

            $questionsPayload[] = [
                'external_id' => (string) Str::uuid(),
                'type' => $item->item_type,
                'prompt' => $item->prompt,
                'choices' => !empty($optionsList) ? array_map(fn ($c) => ['id' => (string) $c['id'], 'text' => (string) $c['text']], $optionsList) : null,
                'correct_choice_ids' => !empty($correctChoiceIds) ? $correctChoiceIds : ($item->correct_answers ?? []),
                'key' => $correctChoiceIds[0] ?? ($item->correct_answers[0] ?? null),
                'explanation' => $item->explanation,
                'reference' => $item->reference,
                'taxonomy' => [
                    'domain' => $item->domain,
                    'bloom_level' => $item->bloom,
                ],
                'irt_3pl' => [
                    'difficulty' => (float) $item->difficulty,
                    'discrimination' => (float) $item->discrimination,
                    'guessing' => (float) $item->guessing,
                ],
                'interactive_payload' => $item->interactive_payload,
                'exhibits' => $item->exhibits,
            ];
        }

        $content = [
            'cert_slug' => $certSlug,
            'exam' => [
                'external_id' => $examUuid,
                'title' => $title,
                'mode' => 'timed',
                'question_count' => count($questionsPayload),
                'time_minutes' => $timeMinutes,
                'pass_score' => $passScore,
                'is_final' => false,
                'irt_enabled' => false,
                'session' => [
                    'external_id' => (string) Str::uuid(),
                    'title' => $title,
                    'number' => 1,
                    'order' => 1,
                    'phase' => 1,
                    'phase_type' => 'lesson',
                    'pass_score' => $passScore,
                ],
            ],
            'questions' => $questionsPayload,
        ];

        $entries = [
            'manifest.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'content.json' => json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];

        // Pack any extracted assets from storage
        $assetDir = storage_path("app/imports/{$batch->id}/assets");
        if (File::isDirectory($assetDir)) {
            $files = File::allFiles($assetDir);
            foreach ($files as $file) {
                $relPath = 'assets/' . $file->getRelativePathname();
                $entries[$relPath] = file_get_contents($file->getRealPath());
            }
        }

        $binary = PsmkZip::build($entries);

        // Validate package before declaring complete
        $tempPsmk = tempnam(sys_get_temp_dir(), 'psmk_val_');
        file_put_contents($tempPsmk, $binary);
        $validation = PackageValidator::validate($tempPsmk);
        @unlink($tempPsmk);

        if (!$validation['valid']) {
            throw new \RuntimeException("Generated package failed validation: " . implode('; ', $validation['errors']));
        }

        $batch->update(['status' => 'converted']);

        return [
            'package_id' => $packageId,
            'filename' => Str::slug($title) . '.psmk',
            'binary' => $binary,
            'size' => strlen($binary),
            'items_count' => count($questionsPayload),
            'assets_count' => count($entries) - 2,
        ];
    }

    /**
     * Directly inserts approved items into a curriculum session.
     */
    public function importBatchToSession(PassimarkImportBatch $batch, PassimarkSession $session): array
    {
        $items = $batch->items()
            ->whereIn('status', ['valid', 'approved'])
            ->orderBy('source_index')
            ->get();

        $inserted = 0;
        foreach ($items as $item) {
            $options = $item->choices ?? [];
            $correctKeys = array_values(array_column(array_filter($options, fn ($c) => $c['is_correct'] ?? false), 'id'));

            PassimarkQuestion::create([
                'session_id' => $session->id,
                'external_id' => (string) Str::uuid(),
                'type' => $item->item_type,
                'content' => $item->prompt,
                'options' => !empty($options) ? array_map(fn ($c) => [
                    'key' => (string) $c['id'],
                    'text' => (string) $c['text'],
                    'is_correct' => (bool) ($c['is_correct'] ?? false),
                ], $options) : [],
                'correct_key' => $correctKeys[0] ?? null,
                'difficulty' => $item->difficulty,
                'discrimination' => $item->discrimination,
                'guessing' => $item->guessing,
                'domain' => $item->domain,
                'bloom_level' => $item->bloom,
                'explanation' => $item->explanation,
                'reference' => $item->reference,
                'interactive_payload' => $item->interactive_payload,
                'exhibits' => $item->exhibits,
            ]);

            $inserted++;
        }

        return ['imported' => $inserted];
    }
}
