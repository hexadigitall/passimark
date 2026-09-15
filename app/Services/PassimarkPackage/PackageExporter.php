<?php

namespace App\Services\PassimarkPackage;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Support\PsmkZip;
use Illuminate\Support\Str;

/**
 * Serializes certification tracks, sessions (modules), and exams into .psmk packages.
 * Learner-safe: answer keys are only included for author packages, never delivered to learners.
 */
final class PackageExporter
{
    public static function exportModule(PassimarkSession $session): array
    {
        $module = self::moduleData($session);
        $content = ['cert_slug' => $session->cert_slug ?? $session->certificationTrack?->slug ?? 'custom', 'module' => $module];

        return self::package('module', $session->title, $content);
    }

    public static function exportExam(PassimarkExam $exam): array
    {
        $session = $exam->session;
        $content = [
            'cert_slug' => $session->cert_slug ?? $session->certificationTrack?->slug ?? 'custom',
            'exam' => [
                'external_id' => (string) $exam->external_id ?: (string) Str::uuid(),
                'title' => $exam->title,
                'mode' => $exam->mode,
                'question_count' => $exam->question_count,
                'time_minutes' => $exam->time_minutes,
                'is_final' => (bool) $exam->is_final,
                'irt_enabled' => (bool) $exam->irt_enabled,
                'session' => self::sessionContext($session),
            ],
            'questions' => $exam->questions()->orderBy('id')->get()->map(fn (PassimarkQuestion $q) => self::questionData($q))->all(),
        ];

        return self::package('exam', $exam->title, $content);
    }

    public static function exportCourse(PassimarkCertificationTrack $track): array
    {
        $modules = $track->sessions()->orderBy('order')->orderBy('id')->get()
            ->map(fn (PassimarkSession $s) => self::moduleData($s))->all();

        $content = ['cert_slug' => $track->slug, 'modules' => $modules];

        return self::package('course', $track->title, $content);
    }

    private static function package(string $contentType, string $title, array $content): array
    {
        $packageId = (string) Str::uuid();
        $now = now()->toRfc3339String();
        $manifest = [
            'format' => PackagingSpec::FORMAT,
            'format_version' => PackagingSpec::FORMAT_VERSION,
            'package_id' => $packageId,
            'content_type' => $contentType,
            'title' => Str::limit($title, 250, ''),
            'created_at' => $now,
            'updated_at' => $now,
            'producer' => ['name' => PackagingSpec::PRODUCER_NAME, 'version' => PackagingSpec::FORMAT_VERSION],
        ];

        $binary = PsmkZip::build([
            'manifest.json' => self::json($manifest),
            'content.json' => self::json($content),
        ]);

        return [
            'package_id' => $packageId,
            'content_type' => $contentType,
            'binary' => $binary,
            'checksum' => hash('sha256', $binary),
            'size' => strlen($binary),
        ];
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private static function sessionContext(PassimarkSession $s): array
    {
        return [
            'external_id' => (string) $s->external_id ?: (string) Str::uuid(),
            'number' => $s->number,
            'phase' => $s->phase,
            'phase_type' => $s->phase_type,
            'order' => $s->order,
            'title' => $s->title,
            'description' => $s->description,
            'domain' => $s->domain,
            'is_open' => (bool) $s->is_open,
            'pass_score' => $s->pass_score,
            'theta_required' => $s->theta_required,
            'time_minutes' => $s->time_minutes,
            'questions_target' => $s->questions_target ?? $s->question_count,
        ];
    }

    private static function moduleData(PassimarkSession $s): array
    {
        return [
            ...self::sessionContext($s),
            'exams' => $s->exams()->orderBy('id')->get()->map(fn (PassimarkExam $e) => [
                'external_id' => (string) $e->external_id ?: (string) Str::uuid(),
                'title' => $e->title,
                'mode' => $e->mode,
                'question_count' => $e->question_count,
                'time_minutes' => $e->time_minutes,
                'is_final' => (bool) $e->is_final,
                'irt_enabled' => (bool) $e->irt_enabled,
            ])->all(),
            'questions' => $s->questions()->orderBy('id')->get()->map(fn (PassimarkQuestion $q) => self::questionData($q))->all(),
        ];
    }

    private static function questionData(PassimarkQuestion $q): array
    {
        $options = is_array($q->options) ? $q->options : [];
        $correctKeys = array_values(array_filter(array_map(fn ($o) => ($o['is_correct'] ?? false) ? ($o['key'] ?? null) : null, $options)));

        return [
            'external_id' => (string) $q->external_id ?: (string) Str::uuid(),
            'type' => PackagingSpec::ITEM_TYPE,
            'prompt' => $q->content,
            'choices' => array_values(array_map(fn ($o) => ['id' => $o['key'] ?? '?', 'text' => $o['text'] ?? ''], $options)),
            'correct_choice_ids' => $correctKeys,
            'key' => $q->correct_key ?? ($correctKeys[0] ?? null),
            'explanation' => $q->explanation,
            'reference' => $q->reference,
            'taxonomy' => ['domain' => $q->domain, 'bloom_level' => $q->bloom_level],
            'irt_3pl' => [
                'difficulty' => $q->difficulty,
                'discrimination' => $q->discrimination,
                'guessing' => $q->guessing,
            ],
        ];
    }
}