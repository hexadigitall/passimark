<?php

namespace App\Services\PassimarkPackage;

use Illuminate\Support\Str;

/**
 * Normative constants and small helpers for the Passimark portable package format (.psmk)
 * as adopted from docs/passimark-file-format-rfc.md (Sprint 9, format_version 1.0).
 */
final class PackagingSpec
{
    public const FORMAT = 'passimark';
    public const FORMAT_VERSION = '1.0';
    public const MAJOR_VERSION = 1;
    public const PRODUCER_NAME = 'Passimark';

    public const CONTENT_TYPES = ['module', 'exam', 'course', 'item-bank'];

    public const MODES = ['timed', 'practice', 'cat'];
    public const PHASE_TYPES = ['cert', 'lesson', 'phase', 'domain', 'mock', 'final'];
    public const ITEM_TYPE = 'single_choice';

    public const ROOT_FILES = ['manifest.json', 'content.json'];

    public const MAX_FILES = 5;
    public const MAX_TOTAL_BYTES = 10 * 1024 * 1024;
    public const MAX_PER_FILE_BYTES = 4 * 1024 * 1024;
    public const MAX_QUESTIONS_PER_MODULE = 1000;
    public const MAX_MODULES_PER_COURSE = 1000;

    public const IRT_B_DIFFICULTY_MIN = -3.0;
    public const IRT_B_DIFFICULTY_MAX = 3.0;
    public const IRT_A_DISCRIMINATION_MIN = 0.01;
    public const IRT_A_DISCRIMINATION_MAX = 3.0;
    public const IRT_C_GUESSING_MIN = 0.0;
    public const IRT_C_GUESSING_MAX = 0.99;

    public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,99}$/';

    public static function isUuid(mixed $value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }

    public static function isRfc3339(mixed $value): bool
    {
        return is_string($value) && strtotime($value) !== false;
    }
}