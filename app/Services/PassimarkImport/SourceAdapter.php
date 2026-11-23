<?php

namespace App\Services\PassimarkImport;

interface SourceAdapter
{
    /**
     * Parse and extract items, metadata, and assets from a source file.
     *
     * @param string $path Absolute path to the source file
     * @param array $options Additional options (e.g. cert_slug, default_domain)
     * @return array{
     *     metadata: array{
     *         title: string,
     *         description?: ?string,
     *         pass_score?: ?int,
     *         time_minutes?: ?int,
     *         cert_slug?: ?string
     *     },
     *     items: array<int, array{
     *         source_index: int,
     *         item_type: string,
     *         prompt: string,
     *         choices?: ?array<int, array{id: string, text: string, is_correct: bool}>,
     *         correct_answers?: ?array<string>,
     *         explanation?: ?string,
     *         reference?: ?string,
     *         domain?: ?string,
     *         bloom?: ?string,
     *         difficulty?: ?float,
     *         discrimination?: ?float,
     *         guessing?: ?float,
     *         interactive_payload?: ?array,
     *         exhibits?: ?array
     *     }>,
     *     assets?: array<string, string>
     * }
     */
    public function extract(string $path, array $options = []): array;
}
