<?php

namespace App\Console\Commands;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\PracticeQuestionBank\UniformQuestionBankGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replace the fabricated "<cert> official exam guide" citation on existing rows.
 *
 * Rewrites references in place rather than re-seeding: the live catalogue is
 * 167k questions and re-seeding would churn 200 tracks worth of ids that
 * learner progress rows point at. Citation composition is delegated to
 * UniformQuestionBankGenerator::referenceFor() so a repair can never disagree
 * with a fresh seed.
 *
 * Two traps this command is built around, both hit in practice:
 *
 *  1. The live database stores cert_key lowercased and with "+" stripped
 *     ("waec-sci", "sec" for SEC+) while the catalogue source is uppercased
 *     ("A-LEVEL-MATH", "SEC+"). A plain equality lookup matches nothing at all
 *     and silently reports "skipped".
 *  2. Deriving deletions from "not present in the catalogue" over-deletes. The
 *     database was seeded from an older catalogue whose codes differ beyond
 *     case, so 18+ genuine tracks are absent from the current source. Removal is
 *     therefore an explicit list, never an absence.
 */
class FixQuestionReferences extends Command
{
    protected $signature = 'passimark:fix-question-references
        {--drop-removed : Also delete the explicitly listed fabricated tracks.}
        {--force : Skip the confirmation prompt.}';

    protected $description = 'Replace fabricated "official exam guide" citations with the real awarding body and blueprint.';

    private const CATALOG_JSON = 'docs/worldwide-205-cert-catalog.json';

    /**
     * Fabricated or duplicated rows removed from the catalogue source, with the
     * reason. Never derived from absence: see the class docblock.
     */
    private const REMOVED = [
        'ARAMCO' => 'Aramco is a company, not an examination',
        'PMP-SA' => 'PMP already exists standalone in USA-BUSINESS; this was a duplicate regional variant',
        'KUWAIT-UE' => 'Not a published named examination',
        'QATAR-UE' => 'Not a published named examination',
        'LIFE-UK-ME' => 'Same test as LIFE-UK, double counted',
    ];

    public function handle(): int
    {
        $path = base_path(self::CATALOG_JSON);

        if (! is_file($path)) {
            $this->components->error("Catalogue source not found: {$path}");

            return self::FAILURE;
        }

        $entries = json_decode((string) file_get_contents($path), true);
        $fabricated = PassimarkQuestion::where('reference', 'like', '%official exam guide%')->count();

        $this->newLine();
        $this->line('  Fabricated citations remaining: <fg=yellow>'.number_format($fabricated).'</>');
        $this->line('  Catalogue entries: '.count($entries));

        if (! $this->option('force') && ! $this->confirm('Rewrite these citations to the real awarding body?', false)) {
            $this->components->warn('Aborted. Nothing was changed.');

            return self::FAILURE;
        }

        $rewritten = 0;
        $already = 0;
        $missing = [];

        foreach ($entries as $entry) {
            $track = $this->findTrack((string) $entry['code']);

            if (! $track) {
                $missing[] = (string) $entry['code'];
                continue;
            }

            $sessionIds = PassimarkSession::where('certification_track_id', $track->id)->pluck('id');

            if ($sessionIds->isEmpty()) {
                continue;
            }

            $wanted = UniformQuestionBankGenerator::referenceFor($entry);

            // The legacy citation was built from the database row's own title,
            // which is not guaranteed to equal the catalogue's current name.
            $legacy = $track->title.' official exam guide';

            $rewritten += PassimarkQuestion::whereIn('session_id', $sessionIds)
                ->where('reference', $legacy)
                ->update(['reference' => $wanted]);

            $already += PassimarkQuestion::whereIn('session_id', $sessionIds)
                ->where('reference', $wanted)
                ->count();
        }

        $this->newLine();
        $this->line('  <fg=green>Rewritten:</> '.number_format($rewritten));
        $this->line('  <fg=gray>Already correct:</> '.number_format($already));

        if ($missing !== []) {
            $this->components->warn(count($missing).' catalogue codes have no track row: '.implode(', ', array_slice($missing, 0, 12)));
        }

        if ($this->option('drop-removed')) {
            $this->newLine();
            $this->dropRemovedTracks();
        }

        $left = PassimarkQuestion::where('reference', 'like', '%official exam guide%')->count();

        $this->newLine();
        if ($left > 0) {
            $this->components->warn(number_format($left).' fabricated citations still present.');

            return self::FAILURE;
        }

        $this->components->info('No fabricated citations remain.');

        return self::SUCCESS;
    }

    private function findTrack(string $code): ?PassimarkCertificationTrack
    {
        return PassimarkCertificationTrack::whereRaw('lower(cert_key) = ?', [self::normalise($code)])->first();
    }

    /**
     * Codes are compared case-insensitively and with "+" removed, because the
     * seeder that produced the live database normalised codes that way:
     * "SEC+" is stored as "sec", "A+" as "a", "PenTest+" as "pentest". Matching
     * on the raw code silently misses all eight CompTIA entries.
     */
    private static function normalise(string $code): string
    {
        return str_replace('+', '', mb_strtolower($code));
    }

    /**
     * Delete only the tracks named in self::REMOVED, with their sessions and
     * questions. Questions go first because progress and attempt-answer rows
     * reference sessions.
     */
    private function dropRemovedTracks(): void
    {
        $found = 0;

        foreach (self::REMOVED as $code => $why) {
            $track = $this->findTrack($code);

            if (! $track) {
                $this->components->twoColumnDetail($code, '<fg=gray>not in database</>');
                continue;
            }

            $sessionIds = PassimarkSession::where('certification_track_id', $track->id)->pluck('id');
            $questions = $sessionIds->isEmpty() ? 0 : PassimarkQuestion::whereIn('session_id', $sessionIds)->count();

            DB::transaction(function () use ($track, $sessionIds) {
                if ($sessionIds->isNotEmpty()) {
                    PassimarkQuestion::whereIn('session_id', $sessionIds)->delete();
                    PassimarkSession::whereIn('id', $sessionIds)->delete();
                }

                $track->delete();
            });

            $found++;
            $this->components->twoColumnDetail(
                $code.' — '.$track->title,
                sprintf('%d sessions, %s questions removed', $sessionIds->count(), number_format($questions))
            );
            $this->line('      <fg=gray>'.$why.'</>');
        }

        if ($found === 0) {
            $this->line('  <fg=gray>No fabricated tracks present in the database.</>');
        }
    }
}
