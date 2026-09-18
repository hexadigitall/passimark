<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\{PassimarkSession, PassimarkExam, PassimarkQuestion, PassimarkProgress, PassimarkCertificationTrack, PassimarkTag, User};
use Illuminate\Support\Facades\Hash;

class CISSPBundleSeeder extends Seeder
{
    public const BUNDLE_JSON = 'seeders/data/cissp/cissp-bundle.json';

    private const ALL_DOMAINS = [
        'Security and Risk Management',
        'Asset Security',
        'Security Architecture and Engineering',
        'Communication and Network Security',
        'Identity and Access Management',
        'Security Assessment and Testing',
        'Security Operations',
        'Software Development Security',
    ];

    /**
     * Textbook sessions that ship narrative/table/flashcard content but no multiple-choice
     * items. They are optional remediation practice: startable, but never required for the
     * approval-gated ladder (see Curriculum::unlockNext). Pools are drawn deterministically
     * from the bundle's real question bank, filtered to the domains each session reviews.
     *
     * @var array<int,array{size:int,domains:list<string>}>
     */
    private const REMEDIATION_POOLS = [
        41 => ['size' => 15, 'domains' => self::ALL_DOMAINS],
        43 => ['size' => 15, 'domains' => self::ALL_DOMAINS],
        44 => ['size' => 15, 'domains' => [
            'Security and Risk Management',
            'Asset Security',
            'Security Architecture and Engineering',
            'Communication and Network Security',
        ]],
    ];

    public function run(): void
    {
        DB::disableQueryLog();
        DB::transaction(function () {
            $result = $this->seedBundle();

            if ($this->command) {
                $this->command->info(sprintf(
                    'CISSP bundle seeded: %d sessions, %d assessment questions (%d remediation), %d drill items (tagged %d domains / %d blooms).',
                    $result['sessions'],
                    $result['questions'],
                    $result['remediation_questions'],
                    $result['drills'],
                    PassimarkTag::where('type', 'domain')->count(),
                    PassimarkTag::where('type', 'bloom')->count()
                ));
            }
        });
    }

    private function seedBundle(): array
    {
        $path = database_path(self::BUNDLE_JSON);
        abort_unless(is_file($path), "CISSP bundle JSON not found: {$path} (run `php artisan passimark:extract-cissp`)");
        $bundle = json_decode(file_get_contents($path), true);

        User::firstOrCreate(['email' => 'student@passimark.com'], ['name' => 'TechPoet Dimeji', 'password' => Hash::make('password')]);
        User::firstOrCreate(['email' => 'admin@passimark.com'], ['name' => 'Passimark Instructor', 'password' => Hash::make('password'), 'role' => 'admin']);

        $trackDef = $bundle['track'];
        $placed = PassimarkCertificationTrack::placeBundle(
            [
                'slug' => $trackDef['slug'],
                'title' => $trackDef['title'],
                'description' => $trackDef['description'],
                'is_active' => $trackDef['is_active'] ?? true,
                'region' => 'USA-IT-SECURITY',
                'advancement' => 'approval',
                'cert_key' => $bundle['cert_key'] ?? $trackDef['slug'],
                'variant_label' => $bundle['variant_label'] ?? $trackDef['title'],
            ],
            'seed:cissp-bundle'
        );
        $track = $placed['track'];
        if (!$track->region) {
            $track->update(['region' => 'USA-IT-SECURITY']);
        }

        PassimarkSession::where('certification_track_id', $track->id)->delete();

        $order = 1;
        $totalQuestions = 0;
        $totalDrills = 0;
        $lastAssessment = null;
        foreach ($bundle['sessions'] as $def) {
            $number = $def['number'];
            $pool = $def['questions'];
            $bloom = $def['bloom_level'];
            $domain = $def['domain'];
            $totalDrills += count($def['drills']);

            $isAssessment = $pool && (stripos($def['title'], 'Diagnostic') !== false || stripos($def['title'], 'Simulat') !== false || stripos($def['title'], 'Mock Exam') !== false);
            $phaseType = $isAssessment ? 'mock' : 'lesson';
            $remediation = self::REMEDIATION_POOLS[$number] ?? null;
            $isOptional = $remediation !== null;

            $timeMinutes = ($def['phase'] ?? 1) >= 4 ? 180 : 90;
            // Remediation pools are generated after the base bank is loaded, but their size is
            // known up front so exams/metadata are staged correctly.
            $qCount = max(count($pool), $remediation['size'] ?? 0);

            $session = PassimarkSession::create([
                'certification_track_id' => $track->id,
                'cert_slug' => $track->slug,
                'number' => $number,
                'phase' => $def['phase'],
                'phase_type' => $phaseType,
                'title' => "Session {$number} • {$def['title']}",
                'description' => $def['description'] ?? ("Session {$number}: " . $def['title']),
                'domain' => $domain,
                'is_open' => $number === 1,
                'is_optional' => $isOptional,
                'order' => $order++,
                'pass_score' => 70,
                'theta_required' => $phaseType === 'mock' ? 0.0 : -0.5,
                'time_limit' => $timeMinutes,
                'time_minutes' => $timeMinutes,
                'question_count' => $qCount,
                'questions_target' => $qCount ?: null,
            ]);

            if ($isAssessment) {
                $lastAssessment = $session;
            }

            $session->tags()->syncWithoutDetaching([
                $this->ensureTag('domain', $domain)->id,
                $this->ensureTag('bloom', $bloom)->id,
            ]);

            if ($pool) {
                $this->createExams($session, count($pool), $timeMinutes);
                $totalQuestions += count($pool);
                $this->seedPool($session, $pool, $domain, $bloom);
            }
        }

        if ($lastAssessment) {
            $lastAssessment->update(['phase_type' => 'final', 'theta_required' => 0.5]);
            PassimarkExam::where('session_id', $lastAssessment->id)->update(['is_final' => true]);
        }

        // Optional remediation sessions draw their pools from the now-loaded base bank.
        $remediation = $this->fillRemediationPools($track);
        $totalQuestions += $remediation['questions'];

        // Open the first assessable step for the demo student using the shared enrollment path.
        $student = User::where('email', 'student@passimark.com')->first();
        \App\Services\Curriculum::enrollInTrack($student, $track);

        return [
            'sessions' => count($bundle['sessions']),
            'questions' => $totalQuestions,
            'remediation_questions' => $remediation['questions'],
            'drills' => $totalDrills,
        ];
    }

    /** Stage the three exam modes for a pool-bearing session (CAT carries the 1.5x time budget). */
    private function createExams(PassimarkSession $session, int $poolSize, int $timeMinutes): void
    {
        $catTime = (int) round($timeMinutes * 1.5);
        foreach (['cat', 'timed', 'practice'] as $mode) {
            PassimarkExam::create([
                'session_id' => $session->id,
                'title' => "{$session->title} - " . strtoupper($mode),
                'mode' => $mode,
                'question_count' => $poolSize,
                'time_minutes' => $mode === 'cat' ? $catTime : ($mode === 'timed' ? $timeMinutes : 0),
                'is_final' => false,
                'irt_enabled' => $mode === 'cat',
            ]);
        }
    }

    /**
     * Generate the optional remediation pools for sessions whose textbook content is narrative
     * only (see REMEDIATION_POOLS). Idempotent: sessions that already carry questions are left
     * untouched, so it is safe to run against a live database without reseeding.
     *
     * @return array{sessions:int,questions:int}
     */
    public function fillRemediationPools(PassimarkCertificationTrack $track): array
    {
        $sessions = $track->sessions()->get()->keyBy('number');
        $sourceSessionIds = PassimarkSession::query()
            ->where('certification_track_id', $track->id)
            ->where('question_count', '>', 0)
            ->whereNotIn('number', array_keys(self::REMEDIATION_POOLS))
            ->pluck('id')
            ->all();

        $filled = 0;
        $questions = 0;
        foreach (self::REMEDIATION_POOLS as $number => $def) {
            $session = $sessions->get($number);
            if (!$session || PassimarkQuestion::where('session_id', $session->id)->exists()) {
                continue;
            }

            $candidates = PassimarkQuestion::whereIn('session_id', $sourceSessionIds)
                ->whereIn('domain', $def['domains'])
                ->orderBy('id')
                ->pluck('id')
                ->all();
            if (count($candidates) < $def['size']) {
                continue;
            }

            $picked = $this->pickDeterministic($candidates, $def['size'], (int) crc32('cissp-remediation|'.$track->id.'|'.$number));
            foreach (PassimarkQuestion::whereIn('id', $picked)->get() as $source) {
                $clone = $source->replicate();
                $clone->session_id = $session->id;
                $clone->exam_id = null;
                $clone->external_id = null;
                $clone->reference = 'Targeted remediation — drawn from the CISSP textbook bank';
                $clone->save();
                $clone->tags()->syncWithoutDetaching([
                    $this->ensureTag('domain', $clone->domain)->id,
                    $this->ensureTag('bloom', $clone->bloom_level)->id,
                ]);
                $questions++;
            }

            $session->update([
                'question_count' => $def['size'],
                'questions_target' => $def['size'],
            ]);
            $this->createExams($session, $def['size'], (int) ($session->time_minutes ?? 180));
            $filled++;
        }

        return ['sessions' => $filled, 'questions' => $questions];
    }

    /**
     * Deterministic sample without replacement. Uses a local LCG so results are reproducible
     * across runs and environments without touching PHP's global RNG state.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function pickDeterministic(array $ids, int $count, int $seed): array
    {
        $ids = array_values($ids);
        $state = ($seed & 0x7fffffff) ?: 1;
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $state = (int) (($state * 1103515245 + 12345) & 0x7fffffff);
            $j = $state % ($i + 1);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return array_slice($ids, 0, $count);
    }

    private function seedPool(PassimarkSession $session, array $pool, string $domain, string $bloom): void
    {
        $n = max(count($pool), 1);
        $pivots = [];
        $domainTagId = $this->ensureTag('domain', $domain)->id;
        $bloomTagId = $this->ensureTag('bloom', $bloom)->id;

        foreach ($pool as $index => $item) {
            $difficulty = round(-0.6 + 0.30 * ($session->phase - 1) + 0.45 * ($index / ($n - 1)), 2);
            PassimarkQuestion::create([
                'session_id' => $session->id,
                'exam_id' => null,
                'content' => $item['stem'],
                'options' => array_map(
                    fn ($o) => ['key' => $o['key'], 'text' => $o['text'], 'is_correct' => $o['key'] === $item['answer']],
                    $item['options']
                ),
                'difficulty' => $difficulty,
                'discrimination' => 1.2,
                'guessing' => 0.25,
                'domain' => $domain,
                'bloom_level' => $bloom,
                'explanation' => $item['explanation'] ?? null,
                'reference' => 'Hexadigitall CISSP Mastery textbook — '.$session->title,
            ]);
        }
        $ids = PassimarkQuestion::where('session_id', $session->id)->pluck('id');
        foreach ($ids as $qid) {
            $pivots[] = ['question_id' => $qid, 'tag_id' => $domainTagId];
            $pivots[] = ['question_id' => $qid, 'tag_id' => $bloomTagId];
        }
        DB::table('passimark_question_tag')->insert($pivots);
    }

    private function ensureTag(string $type, string $label): PassimarkTag
    {
        return PassimarkTag::firstOrCreate(['type' => $type, 'slug' => Str::slug($label)], ['label' => $label]);
    }
}