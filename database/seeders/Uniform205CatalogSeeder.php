<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\{PassimarkCertificationTrack, PassimarkSession, PassimarkExam, PassimarkProgress, User};

/**
 * Uniform 205-cert catalog (METHODOLOGY v2 • UP ADAPTIVE).
 *
 * Source: the SQL-Generator tool catalog (docs/worldwide-205-cert-catalog.json)
 * — 205 certifications across 9 track regions, each expanded to the identical
 * 14-session ladder (v4 spec §4b): cert container → 6 lessons → 2 phase CATs →
 * domain mastery → 3 mocks (70/100/120%) → final real-spec.
 *   205 certs × 14 sessions × 3 exam modes = 2,870 sessions / 8,610 exams.
 *
 * This is the canonical mass-load path for the 205 universe; the 17 flagship
 * certs use the custom WorldwidePassimarkCatalogSeeder instead.
 */
class Uniform205CatalogSeeder extends Seeder
{
    public const CATALOG_JSON = 'docs/worldwide-205-cert-catalog.json';

    public function run(): void
    {
        DB::disableQueryLog();
        DB::transaction(function () {
            $entries = $this->loadEntries();
            $stats = $this->seedCatalog($entries);
            if ($this->command) {
                $this->command->info(sprintf(
                    'Uniform 205 catalog seeded: %d tracks, %d sessions, %d exams across %d regions.',
                    $stats['tracks'], $stats['sessions'], $stats['exams'], $stats['regions']
                ));
            }
        });
    }

    public function loadEntries(): array
    {
        $path = base_path(self::CATALOG_JSON);
        abort_unless(is_file($path), "205-cert catalog JSON not found: {$path}");
        $entries = json_decode(file_get_contents($path), true);

        $limit = (int) env('SEED_LIMIT', 0);
        if ($limit > 0) {
            $entries = array_slice($entries, 0, $limit);
        }

        $regions = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('SEED_REGIONS', '')))));
        $codes = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('SEED_CERTS', '')))));

        return array_values(array_filter($entries, function ($entry) use ($regions, $codes) {
            if ($regions && ! in_array(strtolower($entry['track']), $regions, true)) {
                return false;
            }
            if ($codes && ! in_array(strtolower($entry['code']), $codes, true)) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Build the uniform ladder for a given list of {code,name,track,final_q,final_time}.
     * Returns [tracks, sessions, exams, regions].
     */
    public function seedCatalog(array $entries): array
    {
        $trackCount = $sessionCount = $examCount = 0;
        $regions = [];

        foreach ($entries as $entry) {
            $trackCount++;
            $regions[$entry['track']] = true;
            $track = PassimarkCertificationTrack::updateOrCreate(
                ['slug' => Str::slug($entry['code'])],
                [
                    'title' => $entry['name'],
                    'description' => "Uniform 205 catalog — {$entry['code']} ({$entry['final_q']}Q / {$entry['final_time']}min final).",
                    'region' => $entry['track'],
                    'advancement' => 'auto',
                    'is_active' => true,
                ]
            );

            PassimarkSession::where('certification_track_id', $track->id)->delete();

            $code = $entry['code'];
            $finalQ = $entry['final_q'];
            $finalTime = $entry['final_time'];
            $order = 1;
            $number = 1;

            // order 0: cert container (Phase 0 metadata wrapper)
            $container = $this->session($track, $code, $number++, $order, [
                'phase' => 0, 'phase_type' => 'cert',
                'title' => "{$code} - Complete Certification Track",
                'domain' => 'CERT-OVERVIEW',
                'is_open' => true, 'pass_score' => max(65, $finalQ - 1),
                'time_minutes' => $finalTime, 'questions_target' => $finalQ,
            ]);
            $examCount += $this->createExams($container, $finalQ, $finalTime);

            // orders 1-3: phase 1 lessons
            $lesson1 = $this->lesson($track, $code, $number++, $order, '1', 'Foundations', 'FOUND', 35, true);
            $examCount += $this->createExams($lesson1, 25, 35);
            $lesson2 = $this->lesson($track, $code, $number++, $order, '2', 'Core Concepts', 'CORE', 35, true);
            $examCount += $this->createExams($lesson2, 25, 35);
            $lesson3 = $this->lesson($track, $code, $number++, $order, '3', 'Advanced Basics', 'ADV', 35, true);
            $examCount += $this->createExams($lesson3, 25, 35);

            // orders 4-6: phase 2 lessons
            $lesson4 = $this->lesson($track, $code, $number++, $order, '4', 'Implementation', 'IMPL', 40, true);
            $examCount += $this->createExams($lesson4, 25, 40);
            $lesson5 = $this->lesson($track, $code, $number++, $order, '5', 'Troubleshooting', 'TROUBLE', 40, true);
            $examCount += $this->createExams($lesson5, 25, 40);
            $lesson6 = $this->lesson($track, $code, $number++, $order, '6', 'Mastery & Edge Cases', 'MASTER', 40, false);
            $examCount += $this->createExams($lesson6, 25, 40);

            // orders 7-8: phase CATs (cumulative)
            foreach (['1' => 'Lessons 1-3', '2' => 'Lessons 4-6'] as $phaseNo => $cluster) {
                $phase = $this->session($track, $code, $number++, $order, [
                    'phase' => 3, 'phase_type' => 'phase',
                    'title' => "Phase {$phaseNo} CAT: {$cluster} Cumulative 60Q @UP",
                    'domain' => "{$code}-PHASE{$phaseNo}",
                    'is_open' => false, 'pass_score' => 70,
                    'time_minutes' => 70, 'questions_target' => 60,
                ]);
                $examCount += $this->createExams($phase, 60, 70);
            }

            // order 9: domain mastery
            $domain = $this->session($track, $code, $number++, $order, [
                'phase' => 4, 'phase_type' => 'domain',
                'title' => 'Domain Mastery: Full Domain 75Q @UP Pressure',
                'domain' => "{$code}-DOMAIN",
                'is_open' => false, 'pass_score' => 75,
                'time_minutes' => 90, 'questions_target' => 75,
            ]);
            $examCount += $this->createExams($domain, 75, 90);

            // orders 10-12: mocks 70/100/120%
            $mockConfigs = [
                ['pct' => 0.7, 'label' => 'Pressure', 'pcode' => 'MOCK70'],
                ['pct' => 1.0, 'label' => 'Real Spec', 'pcode' => 'MOCK100'],
                ['pct' => 1.2, 'label' => 'Overload', 'pcode' => 'MOCK120'],
            ];
            foreach ($mockConfigs as $i => $cfg) {
                $mockQ = (int) round($finalQ * $cfg['pct']);
                $mockQ = $cfg['pct'] === 0.7 ? max(15, $mockQ) : $mockQ;
                $mockT = (int) round($finalTime * $cfg['pct']);
                $mock = $this->session($track, $code, $number++, $order, [
                    'phase' => 5, 'phase_type' => 'mock',
                    'title' => "Mock " . ($i + 1) . ": " . $cfg['pct'] * 100 . "% {$cfg['label']}",
                    'domain' => "{$code}-{$cfg['pcode']}",
                    'is_open' => false, 'pass_score' => 70,
                    'time_minutes' => $mockT, 'questions_target' => $mockQ,
                ]);
                $examCount += $this->createExams($mock, $mockQ, $mockT);
            }

            // order 13: final real spec
            $final = $this->session($track, $code, $number++, $order, [
                'phase' => 6, 'phase_type' => 'final',
                'title' => "Final: Real Exam Spec - {$code} ({$finalQ}Q / {$finalTime}min)",
                'domain' => "{$code}-FINAL",
                'is_open' => false, 'pass_score' => 70,
                'time_minutes' => $finalTime, 'questions_target' => $finalQ,
            ]);
            $examCount += $this->createExams($final, $finalQ, $finalTime, true);

            $sessionCount += $order - 1;
        }

        $this->enrollDemoUser();

        return ['tracks' => $trackCount, 'sessions' => $sessionCount, 'exams' => $examCount, 'regions' => count($regions)];
    }

    private function enrollDemoUser(): void
    {
        User::firstOrCreate(['email' => 'student@passimark.com'], ['name' => 'TechPoet Dimeji', 'password' => Hash::make('password')]);
        $student = User::where('email', 'student@passimark.com')->first();
        foreach (PassimarkSession::where('phase_type', 'lesson')->where('is_open', true)->get() as $session) {
            PassimarkProgress::firstOrCreate(
                ['user_id' => $student->id, 'session_id' => $session->id],
                ['status' => 'open', 'ability_theta' => 0, 'attempts' => 0]
            );
        }
    }

    private function lesson(PassimarkCertificationTrack $track, string $code, int $number, int &$order, string $n, string $name, string $pcode, int $time, bool $open): PassimarkSession
    {
        $lesson = $this->session($track, $code, $number, $order, [
            'phase' => $n <= '3' ? 1 : 2, 'phase_type' => 'lesson',
            'title' => "Lesson {$n}: {$name}",
            'domain' => "{$code}-L{$n}-{$pcode}",
            'is_open' => $open, 'pass_score' => 70,
            'time_minutes' => $time, 'questions_target' => 25,
        ]);
        return $lesson;
    }

    private function session(PassimarkCertificationTrack $track, string $code, int $number, int &$order, array $data): PassimarkSession
    {
        return PassimarkSession::create([
            'certification_track_id' => $track->id,
            'cert_slug' => Str::slug($code),
            'number' => $number,
            'phase' => $data['phase'],
            'phase_type' => $data['phase_type'],
            'title' => $data['title'],
            'description' => "{$data['domain']} — uniform v2 ladder.",
            'domain' => $data['domain'],
            'is_open' => $data['is_open'],
            'order' => $order++,
            'pass_score' => $data['pass_score'],
            'theta_required' => null,
            'time_minutes' => $data['time_minutes'],
            'time_limit' => $data['time_minutes'],
            'questions_target' => $data['questions_target'],
            'question_count' => $data['questions_target'],
        ]);
    }

    private function createExams(PassimarkSession $session, int $qCount, int $timeMinutes, bool $isFinal = false): int
    {
        $modes = [
            'cat' => ['title' => 'Adaptive CAT', 'time' => $timeMinutes > 0 ? (int) round($timeMinutes * 1.5) : 0, 'irt' => true],
            'timed' => ['title' => 'Timed Pearson VUE', 'time' => $timeMinutes, 'irt' => false],
            'practice' => ['title' => 'Practice @UP', 'time' => 0, 'irt' => false],
        ];
        foreach ($modes as $mode => $cfg) {
            PassimarkExam::create([
                'session_id' => $session->id,
                'title' => "{$session->title} - {$cfg['title']}",
                'mode' => $mode,
                'question_count' => $qCount,
                'time_minutes' => $cfg['time'],
                'is_final' => $isFinal,
                'irt_enabled' => $cfg['irt'],
            ]);
        }
        return 3;
    }
}