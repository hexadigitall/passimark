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

    public function run(): void
    {
        DB::disableQueryLog();
        DB::transaction(function () {
            $result = $this->seedBundle();

            if ($this->command) {
                $this->command->info(sprintf(
                    'CISSP bundle seeded: %d sessions, %d assessment questions, %d drill items (tagged %d domains / %d blooms).',
                    $result['sessions'],
                    $result['questions'],
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
        $track = PassimarkCertificationTrack::firstOrCreate(
            ['slug' => $trackDef['slug']],
            ['title' => $trackDef['title'], 'description' => $trackDef['description'], 'is_active' => $trackDef['is_active'] ?? true]
        );

        // Replace the track's seeded curriculum with the extracted textbook bundle
        // (idempotent; cascades exams/questions/progress/attempts for the track).
        PassimarkSession::where('certification_track_id', $track->id)->delete();

        $order = 1;
        $totalQuestions = 0;
        $totalDrills = 0;
        foreach ($bundle['sessions'] as $def) {
            $number = $def['number'];
            $pool = $def['questions'];
            $bloom = $def['bloom_level'];
            $domain = $def['domain'];
            $totalDrills += count($def['drills']);

            $session = PassimarkSession::create([
                'certification_track_id' => $track->id,
                'number' => $number,
                'phase' => $def['phase'],
                'order' => $order++,
                'title' => "Session {$number} • {$def['title']}",
                'description' => $def['description'] ?? ("Session {$number}: ".$def['title']),
                'domain' => $domain,
                'is_open' => $number === 1,
                'pass_score' => 70,
                'time_limit' => $def['phase'] == 4 ? 180 : 90,
                'question_count' => max(count($pool), 15),
            ]);

            $session->tags()->syncWithoutDetaching([
                $this->ensureTag('domain', $domain)->id,
                $this->ensureTag('bloom', $bloom)->id,
            ]);

            if ($pool) {
                foreach (['cat', 'timed', 'practice'] as $mode) {
                    PassimarkExam::create([
                        'session_id' => $session->id,
                        'title' => "{$session->title} - ".strtoupper($mode),
                        'mode' => $mode,
                        'question_count' => count($pool),
                    ]);
                }
                $totalQuestions += count($pool);
                $this->seedPool($session, $pool, $domain, $bloom);
            }
        }

        // First session starts open for the demo student; everything else follows the approval-gated ladder.
        $student = User::where('email', 'student@passimark.com')->first();
        $first = PassimarkSession::where('certification_track_id', $track->id)->where('number', 1)->first();
        PassimarkProgress::firstOrCreate(['user_id' => $student->id, 'session_id' => $first->id], ['status' => 'open']);

        return [
            'sessions' => count($bundle['sessions']),
            'questions' => $totalQuestions,
            'drills' => $totalDrills,
        ];
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