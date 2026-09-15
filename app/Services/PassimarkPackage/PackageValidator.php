<?php

namespace App\Services\PassimarkPackage;

use App\Support\PsmkZip;
use Illuminate\Support\Str;

/**
 * Validates a .psmk archive against the format_version 1.0 contract.
 *
 * Returns ["valid", "errors", "warnings", "manifest", "content"]. Errors are human-readable
 * strings suitable for an import preview or audit trail. No records are touched here.
 */
final class PackageValidator
{
    public static function validate(string $path): array
    {
        $base = [
            'manifest' => null,
            'content' => null,
            'warnings' => [],
            'files' => [],
        ];

        if (! is_file($path)) {
            return self::result(false, ['Package file does not exist.'], $base);
        }
        $fileSize = filesize($path);
        if ($fileSize > PackagingSpec::MAX_TOTAL_BYTES) {
            return self::result(false, ['Package exceeds the maximum archive size limit.'], $base);
        }

        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            return self::result(false, ['Package file is empty or unreadable.'], $base);
        }

        $errors = [];
        $warnings = [];

        try {
            $meta = PsmkZip::inspect($binary);
        } catch (\Throwable $e) {
            return self::result(false, [$e->getMessage()], $base);
        }

        if ($meta['count'] > PackagingSpec::MAX_FILES) {
            return self::result(false, ['Package contains too many files.'], $base);
        }

        $names = [];
        $totalUsize = 0;
        foreach ($meta['entries'] as $e) {
            $names[] = $e['name'];
            if (self::unsafeName($e['name'])) {
                return self::result(false, ["Archive contains unsafe path: '{$e['name']}'."], $base);
            }
            if ($e['csize'] > PackagingSpec::MAX_PER_FILE_BYTES) {
                return self::result(false, ["Archive entry '{$e['name']}' exceeds the per-file size limit."], $base);
            }
            $totalUsize += $e['usize'];
        }
        if ($totalUsize > PackagingSpec::MAX_TOTAL_BYTES) {
            return self::result(false, ['Package uncompressed content exceeds the maximum size limit.'], $base);
        }

        foreach ($names as $name) {
            if (! in_array($name, PackagingSpec::ROOT_FILES, true)) {
                $errors[] = "Unexpected entry '{$name}'. Version 1 packages contain exactly " . implode(' and ', PackagingSpec::ROOT_FILES) . '.';
            }
        }
        foreach (PackagingSpec::ROOT_FILES as $required) {
            if (! in_array($required, $names, true)) {
                $errors[] = "Missing required file '{$required}'.";
            }
        }
        if ($errors) {
            return self::result(false, $errors, $base);
        }

        try {
            $files = PsmkZip::read($binary);
        } catch (\Throwable $e) {
            return self::result(false, [$e->getMessage()], $base);
        }

        $manifest = json_decode($files['manifest.json'], true);
        if (! is_array($manifest)) {
            return self::result(false, ['manifest.json is not valid JSON.'], $base);
        }
        $manifestErrors = self::validateManifest($manifest);
        if ($manifestErrors) {
            return self::result(false, $manifestErrors, [...$base, 'manifest' => $manifest]);
        }

        $content = json_decode($files['content.json'], true);
        if (! is_array($content)) {
            return self::result(false, ['content.json is not valid JSON.'], [...$base, 'manifest' => $manifest]);
        }

        $contentErrors = $manifest['content_type'] === 'module'
            ? self::validateModuleContent($content)
            : ($manifest['content_type'] === 'exam'
                ? self::validateExamContent($content)
                : ($manifest['content_type'] === 'course'
                    ? self::validateCourseContent($content)
                    : self::validateItemBankContent($content)));

        $errors = array_merge($errors, $contentErrors);
        $errors = array_merge($errors, self::checkUniqueExternalIds($content));

        return self::result(empty($errors), $errors, [...$base, 'manifest' => $manifest, 'content' => $content, 'warnings' => $warnings]);
    }

    private static function result(bool $valid, array $errors, array $extra): array
    {
        return [
            'valid' => $valid,
            'errors' => array_values(array_unique($errors)),
            'warnings' => $extra['warnings'] ?? [],
            'manifest' => $extra['manifest'] ?? null,
            'content' => $extra['content'] ?? null,
        ];
    }

    private static function unsafeName(string $name): bool
    {
        return str_starts_with($name, '/')
            || $name === '' || $name === '.' || $name === '..'
            || str_contains($name, '..')
            || str_contains($name, '\\')
            || str_contains($name, '/');
    }

    // ------------------------------------------------------------------ manifest

    private static function validateManifest(array $m): array
    {
        $errors = [];
        if (($m['format'] ?? null) !== PackagingSpec::FORMAT) {
            $errors[] = "manifest.format must be 'passimark'.";
        }
        if (! isset($m['format_version']) || ! preg_match('/^(\d+)\.\d+$/', (string) $m['format_version'], $mm)) {
            $errors[] = 'manifest.format_version must be semantic versioning (e.g. "1.0").';
        } elseif ((int) $mm[1] !== PackagingSpec::MAJOR_VERSION) {
            $read = $m['format_version'] ?? '1.x';
            $errors[] = "Unsupported format major version {$mm[1]} (this app reads {$read} as version 1.x).";
        }
        if (! PackagingSpec::isUuid($m['package_id'] ?? null)) {
            $errors[] = 'manifest.package_id must be a UUID.';
        }
        if (! in_array($m['content_type'] ?? null, PackagingSpec::CONTENT_TYPES, true)) {
            $errors[] = 'manifest.content_type must be one of: ' . implode(', ', PackagingSpec::CONTENT_TYPES) . '.';
        }
        if (! is_string($m['title'] ?? null) || trim($m['title']) === '') {
            $errors[] = 'manifest.title is required.';
        } elseif (mb_strlen($m['title']) > 255) {
            $errors[] = 'manifest.title must be 255 characters or fewer.';
        }
        foreach (['created_at', 'updated_at'] as $field) {
            if (! PackagingSpec::isRfc3339($m[$field] ?? null)) {
                $errors[] = "manifest.{$field} must be an RFC 3339 timestamp.";
            }
        }
        if (! is_array($m['producer'] ?? null) || ! is_string($m['producer']['name'] ?? null) || trim($m['producer']['name']) === '') {
            $errors[] = 'manifest.producer.name is required.';
        }
        if (mb_strlen($m['producer']['name'] ?? '') > 120) {
            $errors[] = 'manifest.producer.name must be 120 characters or fewer.';
        }
        return $errors;
    }

    // ------------------------------------------------------------------ questions

    private static function validateQuestion(mixed $q, string $prefix): array
    {
        $errors = [];
        if (! is_array($q)) {
            return ["{$prefix} must be an object."];
        }
        if (! PackagingSpec::isUuid($q['external_id'] ?? null)) {
            $errors[] = "{$prefix}.external_id must be a UUID.";
        }
        if (($q['type'] ?? null) !== PackagingSpec::ITEM_TYPE) {
            $errors[] = "{$prefix}.type must be 'single_choice' in version 1.";
        }
        if (! is_string($q['prompt'] ?? null) || trim($q['prompt']) === '') {
            $errors[] = "{$prefix}.prompt is required.";
        }
        if (! is_array($q['choices'] ?? null) || count($q['choices']) < 2 || count($q['choices']) > 6) {
            $errors[] = "{$prefix}.choices must be an array of 2 to 6 choices.";
        } else {
            $ids = [];
            foreach ($q['choices'] as $i => $choice) {
                $id = $choice['id'] ?? null;
                $text = $choice['text'] ?? null;
                if (! is_string($id) || $id === '' || strlen($id) > 8) {
                    $errors[] = "{$prefix}.choices[{$i}].id must be a non-empty string of at most 8 characters.";
                }
                if (! is_string($text) || trim($text) === '') {
                    $errors[] = "{$prefix}.choices[{$i}].text is required.";
                }
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
            if (count($ids) !== count(array_unique($ids))) {
                $errors[] = "{$prefix}.choices ids must be unique.";
            }
        }
        $correct = $q['correct_choice_ids'] ?? null;
        if (! is_array($correct) || count($correct) !== 1) {
            $errors[] = "{$prefix}.correct_choice_ids must contain exactly one choice id.";
        } else {
            $validIds = array_column(is_array($q['choices'] ?? null) ? $q['choices'] : [], 'id');
            if (! in_array($correct[0], $validIds, true)) {
                $errors[] = "{$prefix}.correct_choice_ids must reference an existing choice id.";
            }
        }
        if (isset($q['key']) && ! in_array($q['key'], array_column(is_array($q['choices'] ?? null) ? $q['choices'] : [], 'id'), true)) {
            $errors[] = "{$prefix}.key must reference an existing choice id.";
        }
        foreach (['explanation', 'reference'] as $textField) {
            if (isset($q[$textField]) && (! is_string($q[$textField]) || mb_strlen($q[$textField]) > 4000)) {
                $errors[] = "{$prefix}.{$textField} must be a string of at most 4000 characters.";
            }
        }
        $tax = $q['taxonomy'] ?? null;
        if (is_array($tax)) {
            if (isset($tax['domain']) && (! is_string($tax['domain']) || mb_strlen($tax['domain']) > 255)) {
                $errors[] = "{$prefix}.taxonomy.domain must be a string of at most 255 characters.";
            }
            if (isset($tax['bloom_level']) && ! is_string($tax['bloom_level'])) {
                $errors[] = "{$prefix}.taxonomy.bloom_level must be a string.";
            }
        }
        $irt = $q['irt_3pl'] ?? null;
        if (is_array($irt)) {
            if (isset($irt['difficulty']) && ! self::within($irt['difficulty'], PackagingSpec::IRT_B_DIFFICULTY_MIN, PackagingSpec::IRT_B_DIFFICULTY_MAX)) {
                $errors[] = "{$prefix}.irt_3pl.difficulty must be within [{-3}, 3].";
            }
            if (isset($irt['discrimination']) && ! self::within($irt['discrimination'], PackagingSpec::IRT_A_DISCRIMINATION_MIN, PackagingSpec::IRT_A_DISCRIMINATION_MAX)) {
                $errors[] = "{$prefix}.irt_3pl.discrimination must be within (0, 3].";
            }
            if (isset($irt['guessing']) && ! self::within($irt['guessing'], PackagingSpec::IRT_C_GUESSING_MIN, PackagingSpec::IRT_C_GUESSING_MAX)) {
                $errors[] = "{$prefix}.irt_3pl.guessing must be within [0, 0.99].";
            }
        }
        return $errors;
    }

    private static function within(mixed $value, float $min, float $max): bool
    {
        return is_int($value) || is_float($value) ? ($value >= $min && $value <= $max) : false;
    }

    // ------------------------------------------------------------------ module

    private static function validateModule(mixed $module, string $prefix = 'module'): array
    {
        $errors = [];
        if (! is_array($module)) {
            return ["{$prefix} must be an object."];
        }
        if (! PackagingSpec::isUuid($module['external_id'] ?? null)) {
            $errors[] = "{$prefix}.external_id must be a UUID.";
        }
        foreach (['number', 'phase', 'order'] as $intField) {
            if (isset($module[$intField]) && ! is_int($module[$intField])) {
                $errors[] = "{$prefix}.{$intField} must be an integer.";
            }
        }
        if (isset($module['phase_type']) && $module['phase_type'] !== null && ! in_array($module['phase_type'], PackagingSpec::PHASE_TYPES, true)) {
            $errors[] = "{$prefix}.phase_type must be one of: " . implode(', ', PackagingSpec::PHASE_TYPES) . '.';
        }
        if (! is_string($module['title'] ?? null) || trim($module['title']) === '') {
            $errors[] = "{$prefix}.title is required.";
        }
        if (isset($module['is_open']) && ! is_bool($module['is_open'])) {
            $errors[] = "{$prefix}.is_open must be a boolean.";
        }
        if (isset($module['pass_score']) && (! is_int($module['pass_score']) || $module['pass_score'] < 0 || $module['pass_score'] > 100)) {
            $errors[] = "{$prefix}.pass_score must be an integer from 0 to 100.";
        }
        if (isset($module['theta_required']) && ! is_int($module['theta_required']) && ! is_float($module['theta_required'])) {
            $errors[] = "{$prefix}.theta_required must be a number or null.";
        }
        if (isset($module['time_minutes']) && $module['time_minutes'] !== null && ! is_int($module['time_minutes'])) {
            $errors[] = "{$prefix}.time_minutes must be an integer or null.";
        }
        if (isset($module['questions_target']) && ! is_int($module['questions_target'])) {
            $errors[] = "{$prefix}.questions_target must be an integer.";
        }

        if (! is_array($module['exams'] ?? null)) {
            $errors[] = "{$prefix}.exams is required.";
        } else {
            foreach ($module['exams'] as $i => $exam) {
                if (! is_array($exam)) {
                    $errors[] = "{$prefix}.exams[{$i}] must be an object.";
                    continue;
                }
                if (! PackagingSpec::isUuid($exam['external_id'] ?? null)) {
                    $errors[] = "{$prefix}.exams[{$i}].external_id must be a UUID.";
                }
                if (! is_string($exam['title'] ?? null) || trim($exam['title']) === '') {
                    $errors[] = "{$prefix}.exams[{$i}].title is required.";
                }
                if (! in_array($exam['mode'] ?? null, PackagingSpec::MODES, true)) {
                    $errors[] = "{$prefix}.exams[{$i}].mode must be one of: " . implode(', ', PackagingSpec::MODES) . '.';
                }
                if (! is_int($exam['question_count'] ?? null) || $exam['question_count'] < 1) {
                    $errors[] = "{$prefix}.exams[{$i}].question_count must be a positive integer.";
                }
                if (isset($exam['time_minutes']) && $exam['time_minutes'] !== null && ! is_int($exam['time_minutes'])) {
                    $errors[] = "{$prefix}.exams[{$i}].time_minutes must be an integer or null.";
                }
                if (isset($exam['is_final']) && ! is_bool($exam['is_final'])) {
                    $errors[] = "{$prefix}.exams[{$i}].is_final must be a boolean.";
                }
                if (isset($exam['irt_enabled']) && ! is_bool($exam['irt_enabled'])) {
                    $errors[] = "{$prefix}.exams[{$i}].irt_enabled must be a boolean.";
                }
            }
        }

        $questions = $module['questions'] ?? null;
        if (! is_array($questions)) {
            $errors[] = "{$prefix}.questions is required.";
        } elseif (count($questions) > PackagingSpec::MAX_QUESTIONS_PER_MODULE) {
            $errors[] = "{$prefix}.questions exceeds the maximum question count.";
        } else {
            foreach ($questions as $i => $q) {
                $errors = array_merge($errors, self::validateQuestion($q, "{$prefix}.questions[{$i}]"));
            }
        }

        return $errors;
    }

    private static function validateModuleContent(array $content): array
    {
        $errors = self::validateCertSlug($content);
        return array_merge($errors, self::validateModule($content['module'] ?? null, 'module'));
    }

    private static function validateExamContent(array $content): array
    {
        $errors = self::validateCertSlug($content);
        $exam = $content['exam'] ?? null;
        if (! is_array($exam)) {
            return array_merge($errors, ['exam is required.']);
        }
        $sess = $exam['session'] ?? null;
        if (! is_array($sess)) {
            $errors[] = 'exam.session is required.';
        } else {
            if (! PackagingSpec::isUuid($sess['external_id'] ?? null)) {
                $errors[] = 'exam.session.external_id must be a UUID.';
            }
            if (! is_string($sess['title'] ?? null) || trim($sess['title']) === '') {
                $errors[] = 'exam.session.title is required.';
            }
        }
        if (! PackagingSpec::isUuid($exam['external_id'] ?? null)) {
            $errors[] = 'exam.external_id must be a UUID.';
        }
        if (! in_array($exam['mode'] ?? null, PackagingSpec::MODES, true)) {
            $errors[] = 'exam.mode must be one of: ' . implode(', ', PackagingSpec::MODES) . '.';
        }
        if (! is_int($exam['question_count'] ?? null) || $exam['question_count'] < 1) {
            $errors[] = 'exam.question_count must be a positive integer.';
        }

        $questions = $content['questions'] ?? null;
        if (! is_array($questions) || count($questions) < 1) {
            $errors[] = 'exam packages require at least one question.';
        } elseif (count($questions) > PackagingSpec::MAX_QUESTIONS_PER_MODULE) {
            $errors[] = 'exam package exceeds the maximum question count.';
        } else {
            foreach ($questions as $i => $q) {
                $errors = array_merge($errors, self::validateQuestion($q, "questions[{$i}]"));
            }
        }
        return $errors;
    }

    private static function validateCourseContent(array $content): array
    {
        $errors = self::validateCertSlug($content);
        $modules = $content['modules'] ?? null;
        if (! is_array($modules) || count($modules) < 1) {
            return array_merge($errors, ['modules must be a non-empty array.']);
        }
        if (count($modules) > PackagingSpec::MAX_MODULES_PER_COURSE) {
            $errors[] = 'modules exceeds the maximum course size.';
        }
        foreach ($modules as $i => $module) {
            $errors = array_merge($errors, self::validateModule($module, "modules[{$i}]"));
        }
        return $errors;
    }

    private static function validateItemBankContent(array $content): array
    {
        $errors = self::validateCertSlug($content);
        $items = $content['items'] ?? null;
        if (! is_array($items) || count($items) < 1) {
            return array_merge($errors, ['item-bank packages require a non-empty items array.']);
        }
        if (count($items) > PackagingSpec::MAX_QUESTIONS_PER_MODULE) {
            $errors[] = 'items exceeds the maximum item count.';
        }
        foreach ($items as $i => $q) {
            $errors = array_merge($errors, self::validateQuestion($q, "items[{$i}]"));
        }
        return $errors;
    }

    private static function checkUniqueExternalIds(array $content): array
    {
        $uuids = [];
        $walk = function ($value) use (&$walk, &$uuids): void {
            if (! is_array($value)) {
                return;
            }
            if (isset($value['external_id']) && is_string($value['external_id']) && Str::isUuid($value['external_id'])) {
                $uuids[$value['external_id']] = ($uuids[$value['external_id']] ?? 0) + 1;
            }
            foreach ($value as $v) {
                $walk($v);
            }
        };
        $walk($content);

        $errors = [];
        foreach ($uuids as $id => $count) {
            if ($count > 1) {
                $errors[] = "Duplicate external_id '{$id}' within the package.";
            }
        }
        return $errors;
    }

    private static function validateCertSlug(array $content): array
    {
        $slug = $content['cert_slug'] ?? null;
        if (! is_string($slug) || ! preg_match(PackagingSpec::SLUG_PATTERN, $slug)) {
            return ['cert_slug is required and must match ' . PackagingSpec::SLUG_PATTERN . '.'];
        }
        return [];
    }
}