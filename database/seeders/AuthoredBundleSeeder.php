<?php

namespace Database\Seeders;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkTag;
use App\Models\User;
use App\Services\AuthoredBundle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Generic loader for authored bundles (see docs/content-format.md).
 *
 * One file per certification in data/bundles/<cert_key>.json. The seeder:
 *  - validates files through AuthoredBundle and refuses to seed an invalid one;
 *  - replaces the synthetic catalog content for that cert_key rather than piling a
 *    -v2 track beside it, so a real CISA bank displaces the fake one;
 *  - builds one session per item domain, sized to the actual items (nothing inflated);
 *  - keeps theta_required null (no body publishes a cut score for this programme) and
 *    keeps difficulty/discrimination/guessing null (they are measurements, not metadata);
 *  - rotates options by a stable hash of the item id so answer keys stay balanced.
 */
class AuthoredBundleSeeder extends Seeder
{
    private const SYNTHETIC_SOURCES = ['seed:uniform-205', 'seed:worldwide-17', 'legacy', null];

    public function run(): void
    {
        $key = $this->bundleOption();

        $keys = $key !== null
            ? [preg_replace('#^.*[\\\\/]#', '', preg_replace('/\.json$/i', '', $key))]
            : $this->discover();

        if ($keys === []) {
            $this->command?->warn('No authored bundles found in database/seeders/data/bundles/.');

            return;
        }

        $totalTracks = $totalSessions = $totalQuestions = 0;

        foreach ($keys as $certKey) {
            try {
                $bundle = new AuthoredBundle($certKey);
            } catch (\RuntimeException $e) {
                $this->command?->error($e->getMessage());
                continue;
            }

            [$errors, $warnings] = $bundle->validate();
            if ($errors !== []) {
                $this->command?->error("Bundled {$certKey} failed validation; skipped.");
                foreach ($errors as $error) {
                    $this->command?->line('  <fg=red>✖</> '.$error);
                }

                continue;
            }

            foreach ($warnings as $warning) {
                $this->command?->line('  <fg=yellow>!</> '.$warning);
            }

            $this->command?->line('  <fg=green>✔</> '.$certKey.' «'.($bundle->data()['title'] ?? $certKey).'» validated.');
            $this->command?->newLine();

            [$sessions, $questions] = $this->seedBundle($bundle);
            $totalTracks++;
            $totalSessions += $sessions;
            $totalQuestions += $questions;
        }

        $this->command?->info(sprintf(
            'Authored bundles seeded: %d tracks, %d sessions, %d questions (options rotated, psychometrics null).',
            $totalTracks,
            $totalSessions,
            $totalQuestions
        ));
    }

    /** @return array{0: int, 1: int} [sessions, questions] */
    private function seedBundle(AuthoredBundle $bundle): array
    {
        $data = $bundle->data();

        return DB::transaction(function () use ($bundle, $data) {
            User::firstOrCreate(['email' => 'student@passimark.com'], [
                'name' => 'TechPoet Dimeji',
                'password' => Hash::make('password'),
            ]);

            $certKey = $bundle->certKey();
            $track = $this->resolveTrack($bundle);

            PassimarkSession::where('certification_track_id', $track->id)->delete();

            $title = $data['title'] ?? Str::title($certKey).' Practice';
            $issuer = $data['issuer'] ?? 'Independent practice bank';
            $outline = isset($data['outline_file']) && is_file(database_path('seeders/data/'.$data['outline_file']))
                ? ' Sessions are weighted by domain per '.$data['outline_file'].'.'
                : '';

            $track->update([
                'title' => $title,
                'description' => 'Authored practice items for '.$title.' ('.$issuer.'). '
                    .($data['content_provenance'] ?? '').$outline,
                'region' => $data['region'] ?? $track->region,
                'advancement' => $data['advancement'] ?? $track->advancement ?? 'approval',
                'variant_label' => $data['variant_label'] ?? $issuer,
                'source' => $bundle->source(),
            ]);

            $sourceLabel = $data['source_label'] ?? 'Authored practice item';
            $firstAssessableId = null;
            $sessionCount = $questionCount = 0;

            foreach ($bundle->domainGroups() as $number => $group) {
                $session = PassimarkSession::create([
                    'certification_track_id' => $track->id,
                    'cert_slug' => $track->slug,
                    'number' => $number + 1,
                    'phase' => 1,
                    'phase_type' => 'lesson',
                    'title' => 'Session '.($number + 1).' • '.$group['domain'],
                    'description' => 'Authored practice items covering '.$group['domain'].' for '.$title.'.',
                    'domain' => $group['domain'],
                    'is_open' => $firstAssessableId === null,
                    'is_optional' => false,
                    'order' => $number + 1,
                    'pass_score' => 70,
                    'theta_required' => null,
                    'time_limit' => 45,
                    'time_minutes' => 45,
                    'question_count' => count($group['items']),
                    'questions_target' => count($group['items']),
                ]);

                $firstAssessableId ??= $session->id;
                $sessionCount++;

                $domainTagId = $this->ensureTag('domain', $group['domain'])->id;
                $pivots = [];

                foreach ($group['items'] as $item) {
                    [$options, $correctKey] = $this->rotateOptions($item);
                    $question = PassimarkQuestion::create([
                        'session_id' => $session->id,
                        'exam_id' => null,
                        'content' => $item['stem'],
                        'options' => $options,
                        'correct_key' => $correctKey,
                        'difficulty' => null,
                        'discrimination' => null,
                        'guessing' => null,
                        'domain' => $group['domain'],
                        'bloom_level' => 'Apply',
                        'explanation' => $item['explanation'],
                        'reference' => $sourceLabel.' — '.$item['reference'],
                    ]);
                    $pivots[] = ['question_id' => $question->id, 'tag_id' => $domainTagId];
                    $questionCount++;
                }

                if ($pivots) {
                    DB::table('passimark_question_tag')->insert($pivots);
                }
            }

            // Re-open the ladder entry point for the demo learner, matching registration;
            // without it the track is unstartable after a demo-reset.
            $student = User::where('email', 'student@passimark.com')->first();
            if ($student && $firstAssessableId) {
                PassimarkProgress::firstOrCreate(
                    ['user_id' => $student->id, 'session_id' => $firstAssessableId],
                    ['status' => 'open', 'ability_theta' => 0, 'attempts' => 0]
                );
            }

            return [$sessionCount, $questionCount];
        });
    }

    /**
     * Find the track this bundle owns: our own previous row on re-seed, else the
     * synthetic/legacy catalog row for this cert (so real content displaces fake
     * content), else a brand-new row.
     */
    private function resolveTrack(AuthoredBundle $bundle): PassimarkCertificationTrack
    {
        $certKey = $bundle->certKey();
        $source = $bundle->source();
        $preferred = $certKey;

        return PassimarkCertificationTrack::where('source', $source)->first()
            ?? PassimarkCertificationTrack::query()
                ->where(fn ($q) => $q->where('cert_key', $certKey)
                    ->orWhere(fn ($fallback) => $fallback->whereNull('cert_key')->where('slug', $preferred)))
                ->whereIn('source', self::SYNTHETIC_SOURCES)
                ->orderByRaw('case when slug = ? then 0 else 1 end', [$preferred])
                ->orderBy('id')
                ->first()
            ?? PassimarkCertificationTrack::placeBundle([
                'slug' => $preferred,
                'title' => $bundle->data()['title'] ?? Str::title($certKey).' Practice',
                'cert_key' => $certKey,
                'variant_label' => $bundle->data()['issuer'] ?? $certKey,
                'region' => $bundle->data()['region'] ?? 'GLOBAL-ACADEMIC',
                'advancement' => 'approval',
                'is_active' => true,
            ], $source)['track'];
    }

    /**
     * @return array{0: array<int, array{key:string,text:string,is_correct:bool}>, 1: string}
     */
    private function rotateOptions(array $item): array
    {
        $texts = array_values($item['options']);
        $shift = crc32($item['id']) % count($texts);

        $rotated = [];
        foreach ($texts as $offset => $text) {
            $rotated[] = $texts[($offset + $shift) % count($texts)];
        }

        $correctIndex = (((int) $item['correct_index']) - $shift + count($texts)) % count($texts);
        $correctKey = chr(65 + $correctIndex);

        $options = [];
        foreach ($rotated as $offset => $text) {
            $key = chr(65 + $offset);
            $options[] = ['key' => $key, 'text' => $text, 'is_correct' => $key === $correctKey];
        }

        return [$options, $correctKey];
    }

    private function ensureTag(string $type, string $label): PassimarkTag
    {
        return PassimarkTag::firstOrCreate(
            ['type' => $type, 'slug' => Str::slug($label)],
            ['label' => $label]
        );
    }

    private function bundleOption(): ?string
    {
        // The seeder can run under a plain db:seed, which declares no --bundle option;
        // only SeedAuthoredBundle does. Fall back to scanning the whole folder otherwise.
        if ($this->command && $this->command->getDefinition()->hasOption('bundle')) {
            $value = $this->command->option('bundle');

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return null;
    }

    /** @return list<string> */
    private function discover(): array
    {
        $files = glob(database_path('seeders/data/bundles/*.json')) ?: [];

        return array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), $files);
    }
}