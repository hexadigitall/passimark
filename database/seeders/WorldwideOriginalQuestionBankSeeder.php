<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\{PassimarkCertificationTrack, PassimarkQuestion, PassimarkSession, PassimarkTag};
use App\Services\PracticeQuestionBank\QuestionBankGenerator;

/**
 * Worldwide 17 Original Question Bank — fills every Worldwide-17 session pool with
 * original practice questions generated deterministically from the request-design
 * catalog (`seeders/data/original-bank/catalog.json`).
 *
 * Design goals:
 *   - Each session receives exactly its `questions_target` count.
 *   - Deterministic: the same (cert, session, question-index) always yields the same
 *     question for the same seed (mulberry32 PRNG) — reproducible and testable.
 *   - The CISSP certificate is skipped: it ships its own real Hexadigitall textbook
 *     bundle via CISSPBundleSeeder.
 *   - Every generated question is tagged (domain + bloom) so the admin
 *     untagged_questions report stays honest.
 */
class WorldwideOriginalQuestionBankSeeder extends Seeder
{
    public const SOURCE = 'seed:worldwide-17';

    /** cert_slug => generator catalog code (CISSP intentionally absent). */
    private const CERT_MAP = [
        'aws-saa' => 'SAA-C03',
        'aws-sap' => 'SAP-C02/C03',
        'az-104' => 'AZ-104',
        'ccna' => 'CCNA',
        'sec' => 'SY0-701',
        'pmp' => 'PMP',
        'acca-f1-f4' => 'ACCA-F1-F4',
        'cfa-l1' => 'CFA-L1',
        'jamb-sci' => 'JAMB',
        'waec-sci' => 'WAEC',
        'ican-skills' => 'ICAN-SKILLS',
        'sat' => 'SAT',
        'ielts' => 'IELTS',
        'gre' => 'GRE',
        'nclex-rn' => 'NCLEX-RN',
        'jee-main' => 'JEE-MAIN',
    ];

    public function run(): void
    {
        DB::disableQueryLog();
        DB::transaction(function () {
            $stats = $this->seedQuestionBanks(self::SOURCE);
            if ($this->command) {
                $this->command->info(sprintf(
                    'Worldwide original question bank seeded: %d certs, %d sessions filled, %d questions.',
                    $stats['certs'], $stats['sessions'], $stats['questions']
                ));
            }
        });
    }

    /**
     * Fill the question pools of every Worldwide-17 session. Sessions that already
     * carry questions are left untouched (idempotent re-runs). Returns stats.
     *
     * @return array{certs:int,sessions:int,questions:int}
     */
    public function seedQuestionBanks(string $source = self::SOURCE): array
    {
        $generator = QuestionBankGenerator::withSeed(0);
        $catalog = $generator->catalog();

        $certs = 0;
        $sessionsFilled = 0;
        $questions = 0;

        $tracks = PassimarkCertificationTrack::where('source', $source)->get();

        foreach ($tracks as $track) {
            $code = self::CERT_MAP[$track->cert_slug ?? $track->slug] ?? null;
            if ($code === null || ! isset($catalog[$code])) {
                continue;
            }
            $cert = $catalog[$code];
            $certs++;

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
     * Deterministically generate exactly $target bank items for one session.
     *
     * @return array<int,array{
     *   objective_reference:string, content:string, options:array<int,array<string,mixed>>,
     *   difficulty:float, discrimination:float, guessing:float,
     *   domain:string, bloom_level:string, explanation:string, reference:string
     * }>
     */
    private function buildPool(array $cert, PassimarkSession $session, int $target): array
    {
        $pool = [];
        for ($i = 0; $i < $target; $i++) {
            $seed = crc32($cert['code'] . '|' . $session->order . '|' . $i);
            $item = QuestionBankGenerator::withSeed($seed)->question($cert, $i);
            $pool[] = $item;
        }
        return $pool;
    }

    /** Bulk-insert a session pool with domain/bloom pivots (CISSP bundle pattern). */
    private function insertPool(PassimarkSession $session, array $pool): void
    {
        $rows = [];
        foreach ($pool as $i => $item) {
            $correct = collect($item['options'])->firstWhere('is_correct', true);
            $rows[] = [
                'session_id' => $session->id,
                'exam_id' => null,
                'external_id' => sprintf('ob-%s-s%02d-q%04d', strtolower(Str::slug($session->cert_slug)), (int) $session->order, $i + 1),
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
            $labels['d:' . $item['domain']] = ['type' => 'domain', 'label' => $item['domain']];
            $labels['b:' . $item['bloom_level']] = ['type' => 'bloom', 'label' => $item['bloom_level']];
        }
        foreach ($labels as $key => $def) {
            $tagIds[$key] = $this->ensureTag($def['type'], $def['label'])->id;
        }

        PassimarkQuestion::query()->insert($rows);
        $questions = PassimarkQuestion::where('session_id', $session->id)
            ->get(['id', 'domain', 'bloom_level']);

        $pivots = [];
        foreach ($questions as $question) {
            $pivots[] = ['question_id' => $question->id, 'tag_id' => $tagIds['d:' . $question->domain]];
            $pivots[] = ['question_id' => $question->id, 'tag_id' => $tagIds['b:' . $question->bloom_level]];
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