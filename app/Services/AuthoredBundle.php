<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Load and validate an authored bundle (docs/content-format.md).
 *
 * One JSON file per certification, in database/seeders/data/bundles/<cert_key>.json.
 * The format is deliberately hostile to the fabrication this project strips out:
 *  - no psychometric keys (difficulty/discrimination/guessing/theta) may appear;
 *  - every item has four options and a correct_index (we rotate, the author points);
 *  - every item carries a real, citable reference and an explanation.
 */
class AuthoredBundle
{
    private array $data;
    private readonly string $path;

    public function __construct(private readonly string $certKey, ?string $path = null)
    {
        $this->path = $path ?? database_path('seeders/data/bundles/'.Str::slug($this->certKey).'.json');

        if (! is_readable($this->path)) {
            throw new \RuntimeException("Authored bundle not found: {$this->path}");
        }

        try {
            $this->data = json_decode(file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Authored bundle is not valid JSON ({$certKey}): {$e->getMessage()}");
        }

        if (! is_array($this->data)) {
            throw new \RuntimeException("Authored bundle for {$certKey} must decode to an object, not ".gettype($this->data).'.');
        }
    }

    /** Absolute file path of the bundle. */
    public function path(): string
    {
        return $this->path;
    }

    /** @return array{0: array, 1: array} [errors, warnings] */
    public function validate(): array
    {
        $errors = [];
        $warnings = [];

        $this->checkTopLevel($errors);
        $this->checkItems($errors, $warnings);

        return [$errors, $warnings];
    }

    public function data(): array
    {
        return $this->data;
    }

    public function items(): array
    {
        return $this->data['items'] ?? [];
    }

    public function certKey(): string
    {
        return $this->certKey;
    }

    public function source(): string
    {
        return 'seed:authored:'.$this->certKey;
    }

    /**
     * Projected answer-key distribution after the seeder rotates options by a
     * stable hash of the item id. Lets an author see balance before seeding.
     *
     * @return array<string, int>
     */
    public function projectedKeyDistribution(): array
    {
        $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        foreach ($this->items() as $item) {
            $count = count($item['options'] ?? []);
            if ($count < 2) {
                continue;
            }
            $shift = crc32($item['id']) % $count;
            $index = (((int) ($item['correct_index'] ?? 0)) - $shift + $count) % $count;
            $counts[chr(65 + $index)]++;
        }

        return $counts;
    }

    /** @return array<int, array{domain: string, items: list<array>}> grouped in file order */
    public function domainGroups(): array
    {
        $groups = [];
        $seen = [];
        $default = $this->data['title'] ?? Str::title($this->certKey).' Practice';
        foreach ($this->items() as $item) {
            $domain = (string) ($item['domain'] ?? $default);
            if (! isset($seen[$domain])) {
                $seen[$domain] = count($groups);
                $groups[] = ['domain' => $domain, 'items' => []];
            }
            $groups[$seen[$domain]]['items'][] = $item;
        }

        return $groups;
    }

    private function checkTopLevel(array &$errors): void
    {
        foreach (['cert_key', 'title', 'issuer', 'content_provenance', 'source_label', 'items'] as $required) {
            if (! isset($this->data[$required]) || (is_string($this->data[$required]) && trim($this->data[$required]) === '')) {
                $errors[] = "Missing top-level field {$required}.";
            }
        }

        if (isset($this->data['cert_key']) && Str::slug($this->data['cert_key']) !== $this->certKey) {
            $errors[] = 'cert_key inside the file ('.Str::slug($this->data['cert_key']).') does not match the file name ('.$this->certKey.').';
        }

        if (! isset($this->data['items']) || ! is_array($this->data['items']) || $this->data['items'] === []) {
            $errors[] = 'items must be a non-empty array.';
        }

        foreach (self::FORBIDDEN_KEYS as $key) {
            if (array_key_exists($key, $this->data)) {
                $errors[] = "Top-level {$key} is a fabricated psychometric value and is not allowed.";
            }
        }
    }

    private function checkItems(array &$errors, array &$warnings): void
    {
        $ids = [];
        $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        $indexA = 0;

        foreach ($this->items() as $i => $item) {
            $where = 'items['.$i.']';
            $id = (string) ($item['id'] ?? '?');
            $this->guardNotArrayOfScalars($item, $where, $errors);

            if (empty($item['id']) || ! is_string($item['id'])) {
                $errors[] = "{$where}: missing or non-string id.";
            } elseif (isset($ids[$item['id']])) {
                $errors[] = "{$where}: duplicate id \"{$id}\".";
            } else {
                $ids[$item['id']] = true;
            }

            if (empty($item['stem']) || ! is_string($item['stem'])) {
                $errors[] = "{$where}: stem missing or empty.";
            }

            $options = $item['options'] ?? null;
            if (! is_array($options) || count($options) !== 4) {
                $errors[] = "{$where} (\"{$id}\"): options must be exactly 4.";
            } else {
                foreach ($options as $o => $text) {
                    if (! is_string($text) || trim($text) === '') {
                        $errors[] = "{$where}: option ".($o + 1).' is empty or not a string.';
                    }
                }
            }

            $ci = $item['correct_index'] ?? null;
            if (! is_int($ci) || $ci < 0 || $ci > 3) {
                $errors[] = "{$where} (\"{$id}\"): correct_index must be an integer 0-3.";
            } else {
                $counts[chr(65 + $ci)]++;
                if ($ci === 0) {
                    $indexA++;
                }
            }

            $expl = $item['explanation'] ?? null;
            if (! is_string($expl) || mb_strlen(trim($expl)) < 20) {
                $errors[] = "{$where} (\"{$id}\"): explanation must be a string of at least 20 characters.";
            }

            $ref = $item['reference'] ?? null;
            if (! is_string($ref) || trim($ref) === '') {
                $errors[] = "{$where} (\"{$id}\"): reference must cite a real, checkable source.";
            } elseif (preg_match('/official exam guide|exam dumps|\d+ questions? (and )?answers?/i', $ref)) {
                $warnings[] = "{$where} (\"{$id}\"): reference smells like a past-paper capture (\"{$ref}\"). Keep it a real source.";
            }

            foreach (self::FORBIDDEN_KEYS as $key) {
                if (array_key_exists($key, $item)) {
                    $errors[] = "{$where} (\"{$id}\"): {$key} is a fabricated psychometric value and is not allowed.";
                }
            }
        }

        $n = max(1, count($this->items()));
        if (($indexA / $n) > 0.70) {
            $warnings[] = sprintf(
                '%.0f%% of items mark option 0 as correct (the artifact this format replaced did exactly that). Rebalance so the correct option is not always the first one.',
                ($indexA / $n) * 100
            );
        }

        $max = max($counts);
        if ($max > 0 && ($max / (array_sum($counts) ?: 1)) > 0.40) {
            $warnings[] = 'Most or all items share source_correct_index; the seeder rotates keys by item id, so this is cosmetic, but writing keys in varied positions is easier to review later.';
        }
    }

    private function guardNotArrayOfScalars(mixed $item, string $where, array &$errors): void
    {
        if (! is_array($item)) {
            $errors[] = "{$where}: each item must be an object, got ".gettype($item).'.';
        }
    }

    /** Psychometric keys that must never appear in an authored file. */
    private const FORBIDDEN_KEYS = [
        'difficulty', 'discrimination', 'guessing', 'theta', 'theta_required',
        'a', 'b', 'c', 'irt', 'psychometrics', 'calibration',
    ];
}