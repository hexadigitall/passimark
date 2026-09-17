<?php

namespace App\Services\PracticeQuestionBank;

/**
 * Deterministic, reproducible PRNG (mulberry32) so that generated question banks
 * are identical for the same seed across runs and environments (no MT_DEFAULT /
 * PHP-version drift). Supports the helper shapes used by the ported catalog
 * generator: next(), range(min,max), pick(array) and shuffle(array).
 */
final class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0xFFFFFFFF;
    }

    public function next(): float
    {
        $this->state = ($this->state + 0x6D2B79F5) & 0xFFFFFFFF;
        $t = $this->state;
        $t = self::imul32($t ^ ($t >> 15), $t | 1);
        $t = $t ^ (($t + self::imul32($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF);
        $t &= 0xFFFFFFFF;
        return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296.0;
    }

    public function range(float $min, float $max): float
    {
        return $min + $this->next() * ($max - $min);
    }

    public function int(int $min, int $max): int
    {
        $count = $max - $min + 1;
        return (int) floor($min + $this->next() * $count);
    }

    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }

    /** Uniform Fisher–Yates shuffle. Returns a new array; input is untouched. */
    public function shuffle(array $items): array
    {
        $out = array_values($items);
        for ($i = count($out) - 1; $i > 0; $i--) {
            $j = $this->int(0, $i);
            [$out[$i], $out[$j]] = [$out[$j], $out[$i]];
        }
        return $out;
    }

    /** 32-bit low-product emulation of JavaScript Math.imul (avoids PHP 64-bit overflow). */
    private static function imul32(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;
        $ah = $a >> 16;
        $al = $a & 0xFFFF;
        $hi = (($ah * $b) & 0xFFFF) << 16;
        $lo = $al * $b;
        return ($hi + $lo) & 0xFFFFFFFF;
    }
}