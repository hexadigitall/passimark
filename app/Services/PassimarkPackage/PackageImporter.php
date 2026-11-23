<?php

namespace App\Services\PassimarkPackage;

use App\Exceptions\PassimarkPackageException;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkPackageImport;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use Illuminate\Support\Facades\DB;

/**
 * Imports .psmk packages. Version 1 is strictly create-only inside a database transaction:
 * it never overwrites an existing session, never touches learner progress, never unlocks
 * progression, and always leaves an audit record in passimark_package_imports.
 *
 * `on_duplicate` governs a package whose checksum already imported:
 *   - `create` (default, V1 behaviour) inserts a second copy;
 *   - `skip`   is a no-op that records a `skipped` audit row (checksum idempotency);
 *   - `fail`   refuses the import and records a `failed` audit row.
 */
final class PackageImporter
{
    public const DUPLICATE_MODES = ['create', 'skip', 'fail'];

    /** `create` always inserts; `update` upserts records by `external_id` (in place). */
    public const IMPORT_MODES = ['create', 'update'];

    /**
     * @param array $opts cert_slug (override target), cert_key, variant_label (bundle category/
     *        variant placement), uploaded_by (User id|null), original_filename, on_duplicate
     * @return array{package_id:string, content_type:string, checksum:string, created:array<string,int>, skipped?:bool}
     */
    public static function import(string $path, array $opts = []): array
    {
        $result = PackageValidator::validate($path);
        if (! $result['valid']) {
            throw new PassimarkPackageException('Invalid package: ' . implode('; ', $result['errors']));
        }

        $checksum = hash('sha256', (string) file_get_contents($path));
        $manifest = $result['manifest'];
        $content = $result['content'];

        $onDuplicate = $opts['on_duplicate'] ?? 'create';
        if (! in_array($onDuplicate, self::DUPLICATE_MODES, true)) {
            throw new PassimarkPackageException("Unknown on_duplicate mode '{$onDuplicate}'.");
        }

        $mode = $opts['mode'] ?? 'create';
        if (! in_array($mode, self::IMPORT_MODES, true)) {
            throw new PassimarkPackageException("Unknown import mode '{$mode}'.");
        }
        $duplicateOf = self::importedCount($checksum);

        if ($duplicateOf > 0 && $onDuplicate === 'skip') {
            PassimarkPackageImport::create([
                'uploaded_by' => $opts['uploaded_by'] ?? null,
                'original_filename' => $opts['original_filename'] ?? basename($path),
                'package_id' => $manifest['package_id'],
                'checksum' => $checksum,
                'content_type' => $manifest['content_type'],
                'summary' => null,
                'status' => 'skipped',
                'error_report' => null,
            ]);

            return [
                'package_id' => $manifest['package_id'],
                'content_type' => $manifest['content_type'],
                'checksum' => $checksum,
                'created' => ['tracks' => 0, 'sessions' => 0, 'exams' => 0, 'questions' => 0],
                'skipped' => true,
            ];
        }

        try {
            if ($duplicateOf > 0 && $onDuplicate === 'fail') {
                throw new PassimarkPackageException(
                    'This package was already imported (checksum ' . substr($checksum, 0, 12) . ').'
                );
            }

            $summary = DB::transaction(function () use ($manifest, $content, $opts, $checksum, $mode) {
                $slug = $opts['cert_slug'] ?? $content['cert_slug'];
                $packageId = $manifest['package_id'] ?? md5($checksum);
                $source = 'import:' . $packageId;

                $created = ['tracks' => 0, 'sessions' => 0, 'exams' => 0, 'questions' => 0];
                $updated = ['sessions' => 0, 'exams' => 0, 'questions' => 0];

                $placed = PassimarkCertificationTrack::placeBundle(
                    [
                        'slug' => $slug,
                        'title' => $manifest['title'],
                        'description' => 'Imported, version 1 .psmk package.',
                        'region' => null,
                        'advancement' => 'approval',
                        'is_active' => true,
                        'cert_key' => $opts['cert_key'] ?? $slug,
                        'variant_label' => $opts['variant_label'] ?? null,
                    ],
                    $source
                );
                $track = $placed['track'];
                $created['tracks'] += $placed['created'] ? 1 : 0;

                if ($manifest['content_type'] === 'module') {
                    self::mergeCounts($created, $updated, self::importModule($content['module'], $track, $slug, $mode));
                } elseif ($manifest['content_type'] === 'exam') {
                    self::mergeCounts($created, $updated, self::importExam($content, $track, $slug, $mode));
                } elseif ($manifest['content_type'] === 'course') {
                    foreach ($content['modules'] as $module) {
                        self::mergeCounts($created, $updated, self::importModule($module, $track, $slug, $mode));
                    }
                } else {
                    throw new PassimarkPackageException('item-bank packages are not importable in version 1.');
                }

                return ['created' => $created, 'updated' => $updated];
            });

            PassimarkPackageImport::create([
                'uploaded_by' => $opts['uploaded_by'] ?? null,
                'original_filename' => $opts['original_filename'] ?? basename($path),
                'package_id' => $manifest['package_id'],
                'checksum' => $checksum,
                'content_type' => $manifest['content_type'],
                'summary' => $mode === 'update' ? $summary['created'] + ['updated' => $summary['updated']] : $summary['created'],
                'status' => 'imported',
            ]);

            return [
                'package_id' => $manifest['package_id'],
                'content_type' => $manifest['content_type'],
                'checksum' => $checksum,
                'created' => $summary['created'],
                'updated' => $summary['updated'],
            ];
        } catch (\Throwable $e) {
            PassimarkPackageImport::create([
                'uploaded_by' => $opts['uploaded_by'] ?? null,
                'original_filename' => $opts['original_filename'] ?? basename($path),
                'package_id' => $manifest['package_id'] ?? null,
                'checksum' => $checksum,
                'content_type' => $manifest['content_type'] ?? null,
                'summary' => null,
                'status' => 'failed',
                'error_report' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }
    }

    /**
     * Validates a package and returns the plan an import would execute, without writing any
     * content. Powers the upload-preview UI and makes duplicate detection inspectable up front.
     *
     * @return array{valid:bool, errors:array<int,string>, warnings:array<int,string>,
     *   cert_slug:?string, counts:array{sessions:int, exams:int, questions:int},
     *   existing_matches:array{sessions:int, exams:int, questions:int},
     *   duplicate:bool, existing_imports:int}
     */
    public static function preview(string $path, array $opts = []): array
    {
        $result = PackageValidator::validate($path);
        $manifest = $result['manifest'];
        $content = $result['content'];

        $checksum = is_file($path) ? hash('sha256', (string) file_get_contents($path)) : null;

        $plan = [
            'valid' => $result['valid'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
            'checksum' => $checksum,
            'package_id' => $manifest['package_id'] ?? null,
            'content_type' => $manifest['content_type'] ?? null,
            'title' => $manifest['title'] ?? null,
            'producer' => $manifest['producer']['name'] ?? null,
            'cert_slug' => $opts['cert_slug'] ?? ($content['cert_slug'] ?? null),
            'counts' => ['sessions' => 0, 'exams' => 0, 'questions' => 0],
            'existing_matches' => ['sessions' => 0, 'exams' => 0, 'questions' => 0],
            'duplicate' => false,
            'existing_imports' => 0,
        ];

        if (! $result['valid'] || ! is_array($manifest) || ! is_array($content)) {
            return $plan;
        }

        $plan['counts'] = self::planCounts($manifest, $content);
        $plan['existing_matches'] = self::existingMatches($manifest, $content);

        if ($checksum !== null) {
            $plan['existing_imports'] = PassimarkPackageImport::where('checksum', $checksum)
                ->where('status', 'imported')
                ->count();
            $plan['duplicate'] = $plan['existing_imports'] > 0;
        }

        return $plan;
    }

    private static function importedCount(string $checksum): int
    {
        return PassimarkPackageImport::where('checksum', $checksum)->where('status', 'imported')->count();
    }

    private static function planCounts(array $manifest, array $content): array
    {
        $counts = ['sessions' => 0, 'exams' => 0, 'questions' => 0];

        switch ($manifest['content_type'] ?? null) {
            case 'module':
                $counts['sessions'] = 1;
                $counts['exams'] = count($content['module']['exams'] ?? []);
                $counts['questions'] = count($content['module']['questions'] ?? []);
                break;
            case 'exam':
                $counts['sessions'] = 1;
                $counts['exams'] = 1;
                $counts['questions'] = count($content['questions'] ?? []);
                break;
            case 'course':
                foreach ($content['modules'] ?? [] as $module) {
                    $counts['sessions'] += 1;
                    $counts['exams'] += count($module['exams'] ?? []);
                    $counts['questions'] += count($module['questions'] ?? []);
                }
                break;
            case 'item-bank':
                $counts['questions'] = count($content['items'] ?? []);
                break;
        }

        return $counts;
    }

    private static function importModule(array $module, PassimarkCertificationTrack $track, string $slug, string $mode = 'create'): array
    {
        $sessionRes = self::upsertSession(self::sessionFields($module), $module['external_id'], $track, $slug, $mode);
        $session = $sessionRes['session'];

        $created = ['sessions' => $sessionRes['created'] ? 1 : 0, 'exams' => 0, 'questions' => 0];
        $updated = ['sessions' => $sessionRes['created'] ? 0 : 1, 'exams' => 0, 'questions' => 0];

        $firstExamId = $session->exams()->orderBy('id')->value('id');
        foreach ($module['exams'] ?? [] as $examData) {
            $examRes = self::upsertExam($examData, $session, $mode);
            $firstExamId ??= $examRes['exam']->id;
            $examRes['created'] ? $created['exams']++ : $updated['exams']++;
        }

        foreach ($module['questions'] ?? [] as $q) {
            $questionRes = self::upsertQuestion($q, $session, $firstExamId, $mode);
            $questionRes['created'] ? $created['questions']++ : $updated['questions']++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private static function importExam(array $content, PassimarkCertificationTrack $track, string $slug, string $mode = 'create'): array
    {
        $examData = $content['exam'];
        $sessionData = $examData['session'];

        $sessionFields = self::sessionFields($sessionData);
        $sessionFields['questions_target'] = $sessionData['questions_target'] ?? $examData['question_count'];

        $sessionRes = self::upsertSession($sessionFields, $sessionData['external_id'], $track, $slug, $mode);
        $session = $sessionRes['session'];

        $created = ['sessions' => $sessionRes['created'] ? 1 : 0, 'exams' => 0, 'questions' => 0];
        $updated = ['sessions' => $sessionRes['created'] ? 0 : 1, 'exams' => 0, 'questions' => 0];

        $examRes = self::upsertExam($examData, $session, $mode);
        $examRes['created'] ? $created['exams']++ : $updated['exams']++;

        foreach ($content['questions'] ?? [] as $q) {
            $questionRes = self::upsertQuestion($q, $session, $examRes['exam']->id, $mode);
            $questionRes['created'] ? $created['questions']++ : $updated['questions']++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private static function upsertSession(array $fields, string $externalId, PassimarkCertificationTrack $track, string $slug, string $mode): array
    {
        if ($mode === 'update') {
            $existing = PassimarkSession::where('external_id', $externalId)
                ->where('certification_track_id', $track->id)
                ->first();
            if ($existing) {
                $existing->update([...$fields, 'certification_track_id' => $track->id, 'cert_slug' => $slug]);
                return ['session' => $existing, 'created' => false];
            }
        }

        $session = PassimarkSession::create([
            ...$fields,
            'certification_track_id' => $track->id,
            'cert_slug' => $slug,
            'external_id' => $externalId,
            'is_open' => false,
        ]);

        return ['session' => $session, 'created' => true];
    }

    private static function upsertExam(array $examData, PassimarkSession $session, string $mode): array
    {
        $fields = self::examFields($examData);

        if ($mode === 'update') {
            $existing = PassimarkExam::where('external_id', $fields['external_id'])
                ->where('session_id', $session->id)
                ->first();
            if ($existing) {
                $existing->update($fields);
                return ['exam' => $existing, 'created' => false];
            }
        }

        $exam = PassimarkExam::create([...$fields, 'session_id' => $session->id]);

        return ['exam' => $exam, 'created' => true];
    }

    private static function upsertQuestion(array $q, PassimarkSession $session, ?int $examId, string $mode): array
    {
        $fields = self::questionFields($q);

        if ($mode === 'update') {
            $existing = PassimarkQuestion::where('external_id', $fields['external_id'])
                ->where('session_id', $session->id)
                ->first();
            if ($existing) {
                $existing->update($fields);
                return ['question' => $existing, 'created' => false];
            }
        }

        $question = PassimarkQuestion::create([
            ...$fields,
            'session_id' => $session->id,
            'exam_id' => $examId,
        ]);

        return ['question' => $question, 'created' => true];
    }

    private static function mergeCounts(array &$created, array &$updated, array $delta): void
    {
        foreach (['sessions', 'exams', 'questions'] as $key) {
            $created[$key] += $delta['created'][$key] ?? 0;
            $updated[$key] += $delta['updated'][$key] ?? 0;
        }
    }

    private static function sessionFields(array $s): array
    {
        return [
            'number' => $s['number'] ?? 0,
            'phase' => $s['phase'] ?? 0,
            'phase_type' => $s['phase_type'] ?? null,
            'order' => $s['order'] ?? 0,
            'title' => $s['title'],
            'description' => $s['description'] ?? null,
            'domain' => $s['domain'] ?? null,
            'pass_score' => $s['pass_score'] ?? 70,
            'theta_required' => $s['theta_required'] ?? null,
            'time_minutes' => $s['time_minutes'] ?? null,
            'questions_target' => $s['questions_target'] ?? $s['question_count'] ?? null,
        ];
    }

    private static function examFields(array $e): array
    {
        return [
            'external_id' => $e['external_id'],
            'title' => $e['title'],
            'mode' => $e['mode'],
            'question_count' => $e['question_count'],
            'time_minutes' => $e['time_minutes'] ?? null,
            'is_final' => $e['is_final'] ?? false,
            'irt_enabled' => $e['irt_enabled'] ?? ($e['mode'] === 'cat'),
        ];
    }

    private static function questionFields(array $q): array
    {
        $correct = array_values(array_intersect(
            $q['correct_choice_ids'] ?? [],
            array_column($q['choices'] ?? [], 'id')
        ));

        $options = array_map(fn ($c) => [
            'key' => $c['id'],
            'text' => $c['text'],
            'is_correct' => in_array($c['id'], $correct, true),
        ], $q['choices'] ?? []);

        return [
            'external_id' => $q['external_id'],
            'type' => $q['type'] ?? 'single_choice',
            'content' => $q['prompt'],
            'options' => $options,
            'correct_key' => $q['key'] ?? ($correct[0] ?? null),
            'difficulty' => $q['irt_3pl']['difficulty'] ?? $q['irt_3pl']['b'] ?? 0,
            'discrimination' => $q['irt_3pl']['discrimination'] ?? $q['irt_3pl']['a'] ?? 1.0,
            'guessing' => $q['irt_3pl']['guessing'] ?? $q['irt_3pl']['c'] ?? 0.25,
            'domain' => $q['taxonomy']['domain'] ?? null,
            'bloom_level' => $q['taxonomy']['bloom_level'] ?? null,
            'explanation' => $q['explanation'] ?? null,
            'reference' => $q['reference'] ?? null,
            'interactive_payload' => $q['interactive_payload'] ?? null,
            'exhibits' => $q['exhibits'] ?? null,
        ];
    }

    private static function existingMatches(array $manifest, array $content): array
    {
        $ids = self::collectExternalIds($manifest, $content);

        return [
            'sessions' => $ids['sessions'] ? PassimarkSession::whereIn('external_id', $ids['sessions'])->count() : 0,
            'exams' => $ids['exams'] ? PassimarkExam::whereIn('external_id', $ids['exams'])->count() : 0,
            'questions' => $ids['questions'] ? PassimarkQuestion::whereIn('external_id', $ids['questions'])->count() : 0,
        ];
    }

    private static function collectExternalIds(array $manifest, array $content): array
    {
        $ids = ['sessions' => [], 'exams' => [], 'questions' => []];

        $collect = function (array $session) use (&$ids) {
            if (! empty($session['external_id'])) {
                $ids['sessions'][] = $session['external_id'];
            }
            foreach ($session['exams'] ?? [] as $exam) {
                if (! empty($exam['external_id'])) {
                    $ids['exams'][] = $exam['external_id'];
                }
            }
            foreach ($session['questions'] ?? [] as $question) {
                if (! empty($question['external_id'])) {
                    $ids['questions'][] = $question['external_id'];
                }
            }
        };

        switch ($manifest['content_type'] ?? null) {
            case 'module':
                $collect($content['module'] ?? []);
                break;
            case 'exam':
                $exam = $content['exam'] ?? [];
                $collect([...($exam['session'] ?? []), 'exams' => [$exam], 'questions' => $content['questions'] ?? []]);
                break;
            case 'course':
                foreach ($content['modules'] ?? [] as $module) {
                    $collect($module);
                }
                break;
            case 'item-bank':
                foreach ($content['items'] ?? [] as $q) {
                    if (! empty($q['external_id'])) {
                        $ids['questions'][] = $q['external_id'];
                    }
                }
                break;
        }

        return $ids;
    }
}