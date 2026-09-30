<?php

namespace Database\Seeders;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Passimark Enterprise Academy — five IT role tracks.
 *
 * Source of truth is data/enterprise/enterprise-roles-authored.json: 66 authored practice
 * items across five roles, extracted from the supplied artifact with nothing reworded.
 *
 * Three defects in the source artifact are corrected here rather than carried over:
 *
 *  1. Answer keys. The artifact set correctIndex = 0 on 66 of 66 items, so a learner who
 *     always picked A scored 100%. Options are rotated per item by a stable hash of the
 *     item id, which moves the correct answer without changing which option is correct.
 *
 *  2. Psychometric parameters. The artifact synthesised difficulty from a -1.5..2.0 range,
 *     discrimination from 0.8..1.8, and a flat 0.25 guessing floor. None of that is a
 *     measurement of these items, and Irt3PL feeds it straight into the adaptive engine,
 *     so all three stay NULL here and Irt3PL uses its own documented defaults.
 *
 *  3. Honest scale. The artifact advertised 4,965 questions built from 66 templates by
 *     re-rendering with shifted numbers, and told the operator to run migrate:fresh --seed.
 *     Session sizes below are derived from the 66 real items, nothing is inflated, no
 *     migration is run, and theta_required stays NULL because no awarding body has published
 *     a cut score for these role programmes.
 */
class EnterpriseRolesSeeder extends Seeder
{
    private const SOURCE = 'seed:enterprise-roles';

    public function run(): void
    {
        $path = database_path('seeders/data/enterprise/enterprise-roles-authored.json');
        if (! is_readable($path)) {
            $this->command?->error("Missing authored bank at {$path}");
            return;
        }

        $bank = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $items = $bank['items'];

        User::firstOrCreate(['email' => 'student@passimark.com'], [
            'name' => 'TechPoet Dimeji',
            'password' => Hash::make('password'),
        ]);

        $byRole = [];
        foreach ($items as $item) {
            $byRole[$item['role']][] = $item;
        }

        $totalQuestions = 0;
        $totalSessions = 0;

        foreach ($bank['roles'] as $code => $meta) {
            $roleItems = $byRole[$code] ?? [];
            if (! $roleItems) {
                continue;
            }

            $placed = PassimarkCertificationTrack::placeBundle([
                'slug' => $meta['slug'],
                'title' => $meta['title'],
                'description' => 'Authored practice items for the '.$meta['title'].' role, aligned to published vendor documentation. Session sizes reflect the number of distinct authored items available.',
                'cert_key' => $meta['slug'],
                'variant_label' => $meta['short'],
                'region' => 'WORLDWIDE-IT',
                'advancement' => 'approval',
                'is_active' => true,
            ], self::SOURCE);

            $track = $placed['track'];

            // Rebuild this track's sessions so re-seeding is idempotent.
            PassimarkSession::where('certification_track_id', $track->id)->delete();

            $byDomain = [];
            foreach ($roleItems as $item) {
                $byDomain[$item['domain'] ?: 'General IT Practice'][] = $item;
            }

            $number = 0;
            $order = 1;
            $firstAssessableId = null;

            foreach ($byDomain as $domain => $domainItems) {
                $number++;
                $session = PassimarkSession::create([
                    'certification_track_id' => $track->id,
                    'cert_slug' => $track->slug,
                    'number' => $number,
                    'phase' => 1,
                    'phase_type' => 'lesson',
                    'title' => "Session {$number} • {$domain}",
                    'description' => "Authored practice items covering {$domain} for the {$meta['title']} role.",
                    'domain' => $domain,
                    'is_open' => $number === 1,
                    'is_optional' => false,
                    'order' => $order++,
                    'pass_score' => 70,
                    // NULL: no awarding body publishes a cut score for this programme.
                    'theta_required' => null,
                    'time_limit' => 45,
                    'time_minutes' => 45,
                    'question_count' => count($domainItems),
                    'questions_target' => count($domainItems),
                ]);

                $firstAssessableId ??= $session->id;
                $totalSessions++;

                $domainTagId = $this->ensureTag('domain', $domain)->id;
                $pivots = [];

                foreach ($domainItems as $item) {
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
                        'domain' => $domain,
                        'bloom_level' => 'Apply',
                        'explanation' => $item['explanation'] ?? null,
                        'reference' => 'Authored practice item — '.$item['reference'],
                    ]);
                    $pivots[] = ['question_id' => $question->id, 'tag_id' => $domainTagId];
                    $totalQuestions++;
                }

                if ($pivots) {
                    DB::table('passimark_question_tag')->insert($pivots);
                }
            }

            // No capstone assessment is created here on purpose. Questions attach to a
            // session directly, so a "mixed" exam would have to duplicate every item to
            // fill itself - exactly the inflation this seeder exists to remove. A role
            // assessment needs a shared item pool, which is a schema change, not a seed.
            // Re-open the ladder entry point for the demo learner, matching what
            // registration does. Without this the track is unstartable after a reset.
            $student = User::where('email', 'student@passimark.com')->first();
            if ($student && $firstAssessableId) {
                PassimarkProgress::firstOrCreate(
                    ['user_id' => $student->id, 'session_id' => $firstAssessableId],
                    ['status' => 'open', 'ability_theta' => 0, 'attempts' => 0]
                );
            }
        }

        $this->command?->info(sprintf(
            'Enterprise Academy seeded: %d roles, %d sessions, %d questions (from %d authored items; options rotated, psychometrics null)',
            count($byRole),
            $totalSessions,
            $totalQuestions,
            count($items)
        ));
    }

    /**
     * Rotate an item's options by a stable hash of its id so the correct answer lands on a
     * different letter per item. The correct option stays correct - only its position moves.
     *
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

        $correctIndex = (((int) $item['source_correct_index']) - $shift + count($texts)) % count($texts);
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
}
