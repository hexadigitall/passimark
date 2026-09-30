<?php

namespace App\Console\Commands;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\PracticeQuestionBank\UniformQuestionBankGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regenerate every generated item in place, from the current generator.
 *
 * The 205 catalogue carries no per-exam objectives, so these items cannot be
 * made into real examination content - see the generator docblock. What this
 * command does fix is everything that was incoherent about them: ungrammatical
 * stems, four interchangeable "correct" options, the answer printed in the
 * question, IT/security distractors served to non-IT examinations, invented
 * companies presented as real organisations, and invented psychometric
 * parameters that the CAT was reading as measurements and turning into
 * pass_probability, and then into issued certificates.
 *
 * Regenerates in place rather than re-seeding so learner progress keeps pointing
 * at the same session and question ids. Deterministic: the same
 * (cert, session, index) yields the same item, so re-running changes nothing.
 */
class RegenerateSyntheticItems extends Command
{
    protected $signature = 'passimark:regenerate-synthetic-items
        {--chunk=200 : Rows per statement.}
        {--force : Skip the confirmation prompt.}';

    protected $description = 'Rewrite generated items: coherent stems, one correct option, no fabricated psychometric parameters.';

    private const CATALOG_JSON = 'docs/worldwide-205-cert-catalog.json';

    /** Rows written per UPDATE statement. */
    private const CHUNK = 200;

    private \PDO $pdo;

    public function handle(): int
    {
        $this->pdo = DB::connection()->getPdo();

        $path = base_path(self::CATALOG_JSON);
        $entries = json_decode((string) file_get_contents($path), true);

        $byCode = [];
        foreach ($entries as $entry) {
            $byCode[self::normalise((string) $entry['code'])] = $entry;
        }

        $total = PassimarkQuestion::count();
        $chunk = max(1, (int) $this->option('chunk'));

        $this->newLine();
        $this->line('  Items to regenerate: <fg=yellow>'.number_format($total).'</>');

        if (! $this->option('force') && ! $this->confirm('Rewrite all generated items?', false)) {
            $this->components->warn('Aborted. Nothing was changed.');

            return self::FAILURE;
        }

        $done = 0;
        $skipped = 0;
        $sessionCount = 0;

        $sessions = PassimarkSession::query()
            ->join('passimark_certification_tracks', 'passimark_certification_tracks.id', '=', 'passimark_sessions.certification_track_id')
            ->select([
                'passimark_sessions.id',
                'passimark_sessions.title',
                'passimark_sessions.domain',
                'passimark_sessions.phase_type',
                'passimark_sessions.order',
                'passimark_sessions.cert_slug',
                'passimark_certification_tracks.cert_key',
            ])
            ->cursor();

        foreach ($sessions as $session) {
            $sessionCount++;

            $code = (string) $session->cert_key;
            $entry = $byCode[self::normalise($code)] ?? null;

            if ($entry === null) {
                $skipped += PassimarkQuestion::where('session_id', $session->id)->count();
                continue;
            }

            $context = [
                'title' => (string) $session->title,
                'domain' => (string) ($session->domain ?: $entry['code']),
                'phase_type' => (string) ($session->phase_type ?: 'lesson'),
            ];

            $questions = PassimarkQuestion::where('session_id', $session->id)
                ->orderBy('id')
                ->get(['id', 'external_id']);

            $rows = [];
            $fallback = 0;

            $slug = Str::slug((string) $session->cert_slug);

            foreach ($questions as $question) {
                // external_id is "synth-<slug>-s<order>-q<n>"; n-1 is the index the
                // seeder originally used to build the seed.
                if (preg_match('/q(\d+)$/', (string) $question->external_id, $m)) {
                    $index = (int) $m[1] - 1;
                } else {
                    $index = $fallback;
                }
                $fallback = $index + 1;

                $seed = crc32('uniform|'.$entry['code'].'|'.$session->order.'|'.$index);
                $item = UniformQuestionBankGenerator::withSeed($seed)->question($entry, $context, $index);

                $correct = collect($item['options'])->firstWhere('is_correct', true);

                $rows[] = [
                    'id' => $question->id,
                    'content' => $item['content'],
                    'options' => json_encode($item['options']),
                    'correct_key' => $correct['key'] ?? null,
                    'difficulty' => $item['difficulty'],
                    'discrimination' => $item['discrimination'],
                    'guessing' => $item['guessing'],
                    'explanation' => $item['explanation'],
                    'reference' => $item['reference'],
                    'external_id' => sprintf('synth-%s-s%02d-q%04d', $slug, (int) $session->order, $index + 1),
                ];
            }

            foreach (array_chunk($rows, $chunk) as $batch) {
                $this->updateBatch($batch);
                $done += count($batch);
            }

            if ($sessionCount % 200 === 0) {
                $this->line('    ...'.number_format($done).' of '.number_format($total));
            }
        }

        $this->newLine();
        $this->line('  <fg=green>Regenerated:</> '.number_format($done));

        if ($skipped > 0) {
            $this->components->warn(number_format($skipped).' items skipped: no catalogue entry for their track.');
        }

        $remaining = PassimarkQuestion::whereNotNull('difficulty')
            ->orWhere('external_id', 'like', 'ub-%')
            ->count();

        if ($remaining > 0) {
            $this->components->warn(number_format($remaining).' items still carry old psychometric values or an old external_id.');
        } else {
            $this->components->info('No item carries a fabricated psychometric value.');
        }

        return self::SUCCESS;
    }

    /**
     * Case-insensitive and "+"-insensitive, because the seeder that produced the
     * live database normalised codes that way: SEC+ is stored as "sec", A+ as
     * "a", PenTest+ as "pentest". Without this the eight CompTIA tracks are
     * silently skipped.
     */
    private static function normalise(string $code): string
    {
        return str_replace('+', '', mb_strtolower($code));
    }

    /** @param array<int,array<string,mixed>> $batch */
    private function updateBatch(array $batch): void
    {
        $ids = array_column($batch, 'id');
        $assign = function (string $column) use ($batch): string {
            $parts = [];
            foreach ($batch as $row) {
                $value = $row[$column];
                if ($value === null) {
                    $parts[] = 'WHEN '.$row['id'].' THEN NULL';
                } elseif (is_int($value) || is_float($value)) {
                    $parts[] = 'WHEN '.$row['id'].' THEN '.$value;
                } else {
                    $parts[] = 'WHEN '.$row['id'].' THEN '.$this->pdo->quote((string) $value);
                }
            }

            return 'CASE id '.implode(' ', $parts).' END';
        };

        $sql = 'UPDATE passimark_questions SET'
            .' content = '.$assign('content')
            .', options = '.$assign('options')
            .', correct_key = '.$assign('correct_key')
            .', difficulty = '.$assign('difficulty')
            .', discrimination = '.$assign('discrimination')
            .', guessing = '.$assign('guessing')
            .', explanation = '.$assign('explanation')
            .', reference = '.$assign('reference')
            .', external_id = '.$assign('external_id')
            .', updated_at = '.$this->pdo->quote(now()->toDateTimeString())
            .' WHERE id IN ('.implode(',', $ids).')';

        $this->pdo->exec($sql);
    }
}
