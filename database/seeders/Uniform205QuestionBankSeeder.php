<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\{PassimarkCertificationTrack, PassimarkQuestion, PassimarkSession, PassimarkTag};
use App\Services\PracticeQuestionBank\UniformQuestionBankGenerator;

/**
 * Uniform 205-catalog question bank — fills every Uniform205CatalogSeeder session pool
 * with deterministic original practice questions.
 *
 * The 205 catalog is the canonical mass-load path (205 certs × 14 sessions), so its
 * questions are generated from each session's own context (title + domain + phase) via
 * {@see UniformQuestionBankGenerator} rather than from a hand-authored cert catalog.
 *
 * Design goals (mirrors WorldwideOriginalQuestionBankSeeder):
 *   - Each session receives exactly its `questions_target` count.
 *   - Deterministic: the same (cert, session, index) always yields the same item.
 *   - Idempotent: sessions that already carry questions are skipped, so it backfills a
 *     live database without disturbing existing pools.
 *   - Every generated question is tagged (domain + bloom) so the admin untagged-questions
 *     report stays honest.
 */
class Uniform205QuestionBankSeeder extends Seeder
{
    public const SOURCE = 'seed:uniform-205';

    public function run(): void
    {
        DB::disableQueryLog();
        DB::transaction(function () {
            $stats = $this->seedQuestionBanks(self::SOURCE);
            if ($this->command) {
                $this->command->info(sprintf(
                    'Uniform 205 question bank seeded: %d certs, %d sessions filled, %d questions.',
                    $stats['certs'], $stats['sessions'], $stats['questions']
                ));
            }
        });
    }

    /**
     * Fill the question pools of every Uniform-205 session. Sessions that already carry
     * questions are left untouched (idempotent re-runs). Returns stats.
     *
     * @return array{certs:int,sessions:int,questions:int}
     */
    public function seedQuestionBanks(string $source = self::SOURCE): array
    {
        $certs = 0;
        $sessionsFilled = 0;
        $questions = 0;

        $tracks = PassimarkCertificationTrack::where('source', $source)->get();

        foreach ($tracks as $track) {
            $certs++;
            $cert = [
                'code' => (string) ($track->cert_key ?: $track->slug),
                'name' => (string) $track->title,
                'track' => (string) $track->region,
            ];

            foreach ($track->sessions()->orderBy('order')->get() as $session) {
                $target = (int) $session->questions_target;
                if ($target <= 0 || PassimarkQuestion::where('session_id', $session->id)->exists()) {
                    continue;
                }
                $pool = $this->buildPool($cert, $session, $target);
                $this->insertPool($session, $pool);
                $sessionsFilled++;
                $questions += count($pool);
            }
        }

        return ['certs' => $certs, 'sessions' => $sessionsFilled, 'questions' => $questions];
    }

    /**
     * Deterministically generate exactly $target items for one session.
     *
     * @param array{code:string,name:string,track:string} $cert
     * @return array<int,array<string,mixed>>
     */
    private function buildPool(array $cert, PassimarkSession $session, int $target): array
    {
        $context = [
            'title' => (string) $session->title,
            'domain' => (string) ($session->domain ?: $cert['code']),
            'phase_type' => (string) ($session->phase_type ?: 'lesson'),
        ];

        $pool = [];
        for ($i = 0; $i < $target; $i++) {
            $seed = crc32('uniform|'.$cert['code'].'|'.$session->order.'|'.$i);
            $pool[] = UniformQuestionBankGenerator::withSeed($seed)->question($cert, $context, $i);
        }

        return $pool;
    }

    /** Bulk-insert a session pool with domain/bloom pivots (CISSP bundle pattern). */
    private function insertPool(PassimarkSession $session, array $pool): void
    {
        $slug = strtolower(Str::slug((string) $session->cert_slug));
        $rows = [];
        foreach ($pool as $i => $item) {
            $correct = collect($item['options'])->firstWhere('is_correct', true);
            $rows[] = [
                'session_id' => $session->id,
                'exam_id' => null,
                'external_id' => sprintf('ub-%s-s%02d-q%04d', $slug, (int) $session->order, $i + 1),
                'content' => $item['content'],
                'options' => json_encode($item['options']),
                'correct_key' => $correct['key'] ?? null,
                'difficulty' => $item['difficulty'],
                'discrimination' => $item['discrimination'],
                'guessing' => $item['guessing'],
                'domain' => $item['domain'],
                'bloom_level' => $item['bloom_level'],
                'explanation' => $item['explanation'],
                'reference' => $item['reference'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $tagIds = [];
        $labels = [];
        foreach ($pool as $item) {
            $labels['d:'.$item['domain']] = ['type' => 'domain', 'label' => $item['domain']];
            $labels['b:'.$item['bloom_level']] = ['type' => 'bloom', 'label' => $item['bloom_level']];
        }
        foreach ($labels as $key => $def) {
            $tagIds[$key] = $this->ensureTag($def['type'], $def['label'])->id;
        }

        PassimarkQuestion::query()->insert($rows);
        $questions = PassimarkQuestion::where('session_id', $session->id)
            ->get(['id', 'domain', 'bloom_level']);

        $pivots = [];
        foreach ($questions as $question) {
            $pivots[] = ['question_id' => $question->id, 'tag_id' => $tagIds['d:'.$question->domain]];
            $pivots[] = ['question_id' => $question->id, 'tag_id' => $tagIds['b:'.$question->bloom_level]];
        }
        if ($pivots) {
            DB::table('passimark_question_tag')->insert($pivots);
        }
    }

    private function ensureTag(string $type, string $label): PassimarkTag
    {
        return PassimarkTag::firstOrCreate(['type' => $type, 'slug' => Str::slug($label)], ['label' => $label]);
    }
}
