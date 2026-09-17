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
 */
final class PackageImporter
{
    /**
     * @param array $opts cert_slug (override target), cert_key, variant_label (bundle category/
     *        variant placement), uploaded_by (User id|null), original_filename
     * @return array{package_id:string, content_type:string, checksum:string, created:array<string,int>}
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

        try {
            $summary = DB::transaction(function () use ($manifest, $content, $opts, $checksum) {
                $slug = $opts['cert_slug'] ?? $content['cert_slug'];
                $packageId = $manifest['package_id'] ?? md5($checksum);
                $source = 'import:' . $packageId;

                $created = ['tracks' => 0, 'sessions' => 0, 'exams' => 0, 'questions' => 0];

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
                    $m = self::importModule($content['module'], $track, $slug);
                    $created['sessions'] += 1;
                    $created['exams'] += $m['exams'];
                    $created['questions'] += $m['questions'];
                } elseif ($manifest['content_type'] === 'exam') {
                    $e = self::importExam($content, $track, $slug);
                    $created['sessions'] += 1;
                    $created['exams'] += 1;
                    $created['questions'] += $e['questions'];
                } elseif ($manifest['content_type'] === 'course') {
                    foreach ($content['modules'] as $module) {
                        $m = self::importModule($module, $track, $slug);
                        $created['sessions'] += 1;
                        $created['exams'] += $m['exams'];
                        $created['questions'] += $m['questions'];
                    }
                } else {
                    throw new PassimarkPackageException('item-bank packages are not importable in version 1.');
                }

                return $created;
            });

            PassimarkPackageImport::create([
                'uploaded_by' => $opts['uploaded_by'] ?? null,
                'original_filename' => $opts['original_filename'] ?? basename($path),
                'package_id' => $manifest['package_id'],
                'checksum' => $checksum,
                'content_type' => $manifest['content_type'],
                'summary' => $summary,
                'status' => 'imported',
            ]);

            return [
                'package_id' => $manifest['package_id'],
                'content_type' => $manifest['content_type'],
                'checksum' => $checksum,
                'created' => $summary,
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

    private static function importModule(array $module, PassimarkCertificationTrack $track, string $slug): array
    {
        $session = PassimarkSession::create([
            ...self::sessionFields($module),
            'certification_track_id' => $track->id,
            'cert_slug' => $slug,
            'external_id' => $module['external_id'],
            'is_open' => false,
        ]);

        $firstExamId = null;
        foreach ($module['exams'] ?? [] as $examData) {
            $exam = PassimarkExam::create([
                'session_id' => $session->id,
                'external_id' => $examData['external_id'],
                'title' => $examData['title'],
                'mode' => $examData['mode'],
                'question_count' => $examData['question_count'],
                'time_minutes' => $examData['time_minutes'] ?? null,
                'is_final' => $examData['is_final'] ?? false,
                'irt_enabled' => $examData['irt_enabled'] ?? ($examData['mode'] === 'cat'),
            ]);
            $firstExamId ??= $exam->id;
        }

        $questionCount = 0;
        foreach ($module['questions'] ?? [] as $q) {
            $question = PassimarkQuestion::create([
                ...self::questionFields($q),
                'session_id' => $session->id,
                'exam_id' => $firstExamId,
            ]);
            $questionCount += 1;
        }

        return ['exams' => count($module['exams'] ?? []), 'questions' => $questionCount];
    }

    private static function importExam(array $content, PassimarkCertificationTrack $track, string $slug): array
    {
        $examData = $content['exam'];
        $sessionData = $examData['session'];

        $session = PassimarkSession::create([
            ...self::sessionFields($sessionData),
            'certification_track_id' => $track->id,
            'cert_slug' => $slug,
            'external_id' => $sessionData['external_id'],
            'is_open' => false,
            'questions_target' => $sessionData['questions_target'] ?? $examData['question_count'],
        ]);

        $exam = PassimarkExam::create([
            'session_id' => $session->id,
            'external_id' => $examData['external_id'],
            'title' => $examData['title'],
            'mode' => $examData['mode'],
            'question_count' => $examData['question_count'],
            'time_minutes' => $examData['time_minutes'] ?? null,
            'is_final' => $examData['is_final'] ?? false,
            'irt_enabled' => $examData['irt_enabled'] ?? ($examData['mode'] === 'cat'),
        ]);

        foreach ($content['questions'] ?? [] as $q) {
            PassimarkQuestion::create([
                ...self::questionFields($q),
                'session_id' => $session->id,
                'exam_id' => $exam->id,
            ]);
        }

        return ['questions' => count($content['questions'] ?? [])];
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
        ];
    }
}