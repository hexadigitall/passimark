<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExtractCisspBundle extends Command
{
    protected $signature = 'passimark:extract-cissp
        {path? : Path to the Hexadigitall CISSP textbook index.html (default: D:\Downloads\CISSP\CISSP\index.html)}
        {--out= : Output JSON path (default: database/seeders/data/cissp/cissp-bundle.json)}';

    protected $description = 'Parse the Hexadigitall Enterprise Security Architecture & CISSP Mastery textbook and extract its assessment items into a structured CISSP bundle JSON.';

    private const INDEX_DEFAULT = 'D:\Downloads\CISSP\CISSP\index.html';

    private const PHASES = [
        1 => 'Foundational Domain Mastery & Core Principles',
        2 => 'Applied Security Engineering & Operations',
        3 => 'Advanced Synthesis, Managerial Mindset & Domain Mastery',
        4 => 'Final CAT Simulation, Readiness Calibration & Exam Execution',
    ];

    private const DOMAINS = [
        1=>'Security and Risk Management',2=>'Security and Risk Management',3=>'Security and Risk Management',4=>'Security and Risk Management',
        5=>'Asset Security',6=>'Asset Security',
        7=>'Security Architecture and Engineering',8=>'Security Architecture and Engineering',9=>'Security Architecture and Engineering',10=>'Security Architecture and Engineering',
        11=>'Communication and Network Security',12=>'Communication and Network Security',13=>'Communication and Network Security',
        14=>'Identity and Access Management',15=>'Benchmark',
        16=>'Identity and Access Management',
        17=>'Security Assessment and Testing',18=>'Security Assessment and Testing',
        19=>'Security Operations',20=>'Security Operations',21=>'Security Operations',22=>'Security Operations',
        23=>'Software Development Security',24=>'Software Development Security',25=>'Software Development Security',
        26=>'Security and Risk Management',27=>'Security Operations',28=>'Security Architecture and Engineering',29=>'Security Operations',
        30=>'Benchmark',
        31=>'Security and Risk Management',32=>'Security Architecture and Engineering',33=>'Communication and Network Security',34=>'Security Operations',
        35=>'Security Architecture and Engineering',36=>'Security and Risk Management',37=>'Security and Risk Management',38=>'Security Architecture and Engineering',39=>'Security and Risk Management',
        40=>'CAT Simulation',41=>'CAT Simulation',42=>'CAT Simulation',43=>'CAT Simulation',44=>'CAT Simulation',45=>'CAT Simulation',46=>'Conclusion',
    ];

    private const TITLE_OVERRIDES = [
        15 => 'Phase 1 Diagnostic Exam (Domains 1-5)',
        30 => 'Phase 2 Full-Length Simulated CAT Exam',
        40 => 'Full-Length CAT Simulation Mock Exam #1',
        41 => 'Error Analysis & Targeted Remediation I',
        42 => 'Full-Length CAT Simulation Mock Exam #2',
        43 => 'Error Analysis & Targeted Remediation II',
        44 => 'High-Yield Concept Blitz',
        45 => 'CAT Mechanics, Pacing & Pre-Exam Protocol',
        46 => 'CAT Pacing Strategy & Executive Summary',
    ];

    private const BLOOM_BY_PHASE = [1 => 'Apply', 2 => 'Analyze', 3 => 'Evaluate', 4 => 'Evaluate'];

    public function handle(): int
    {
        $path = $this->argument('path') ?? self::INDEX_DEFAULT;
        if (! is_file($path)) {
            $this->error("Textbook not found at: {$path}");
            $this->error('Pass the full path to index.html, e.g. passimark:extract-cissp D:\Downloads\CISSP\CISSP\index.html');
            return self::FAILURE;
        }

        $out = $this->option('out') ?? database_path('seeders/data/cissp/cissp-bundle.json');
        $html = file_get_contents($path);

        $sections = $this->parseSections($html);
        $sessionMap = $this->mapSessions($sections);
        $questions = $this->parseQuestions($html);

        $bySession = $this->groupBySession($questions, $sections, $sessionMap, $html);

        $sessions = [];
        $warnings = [];
        foreach ($bySession as $number => $data) {
            $sessions[] = $this->buildSession($number, $data, $sections, $sessionMap, $html, $warnings);
        }
        usort($sessions, fn ($a, $b) => $a['number'] <=> $b['number']);

        $payload = [
            'source' => 'Hexadigitall “Enterprise Security Architecture & CISSP Mastery” (45-Day Prep Curriculum)',
            'source_file' => basename($path),
            'extractor' => 'passimark:extract-cissp',
            'track' => [
                'slug' => 'cissp',
                'title' => 'CISSP',
                'description' => 'Certified Information Systems Security Professional 45-day preparation track extracted from the Hexadigitall textbook (Enterprise Security Architecture & CISSP Mastery).',
                'is_active' => true,
            ],
            'phases' => collect(self::PHASES)->map(fn ($title, $phase) => [
                'phase' => $phase,
                'title' => $title,
                'session_count' => collect($sessions)->where('phase', $phase)->count(),
                'question_count' => collect($sessions)->where('phase', $phase)->sum('pool_size'),
            ])->values()->all(),
            'sessions' => $sessions,
            'stats' => [
                'session_count' => count($sessions),
                'question_count' => collect($sessions)->sum('pool_size'),
                'drill_count' => collect($sessions)->where('assessment_type', 'drill')->count(),
                'benchmark_count' => collect($sessions)->whereIn('assessment_type', ['benchmark', 'simulated_cat', 'mock_exam_1', 'mock_exam_2'])->count(),
                'warning_count' => count($warnings),
            ],
            'warnings' => $warnings,
        ];

        $payload['stats']['total_question_items'] = collect($sessions)->sum(fn ($s) => count($s['questions']));

        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0755, true);
        }
        file_put_contents($out, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info('Extracted CISSP bundle to '.$out);
        $this->line(sprintf('Sessions: %d | Question items: %d | Pools: %d drills + %d exam/benchmark | Warnings: %d',
            $payload['stats']['session_count'],
            $payload['stats']['total_question_items'],
            $payload['stats']['drill_count'],
            $payload['stats']['benchmark_count'],
            count($warnings)
        ));
        foreach (array_slice($warnings, 0, 25) as $w) {
            $this->warn('  - '.$w);
        }

        return self::SUCCESS;
    }

    private function parseSections(string $html): array
    {
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/s', $html, $m, PREG_OFFSET_CAPTURE);
        $sections = [];
        foreach ($m[0] as $i => $full) {
            $title = $this->clean($m[1][$i][0]);
            $sections[] = ['offset' => $full[1], 'title' => $title, 'session' => null];
        }
        return $sections;
    }

    private function mapSessions(array &$sections): array
    {
        $last = 0;
        $map = [];
        foreach ($sections as $i => $section) {
            $t = $section['title'];
            if (! preg_match('/^(\d+)\.(\d+)[a-z]?\b/', $t, $p)) {
                continue;
            }
            $n = (int) $p[1];
            $part = (int) $p[2];
            if ($part === 1) {
                $session = $n > $last ? $n : $last + 1;
            } else {
                $session = $last;
            }
            $last = $session;
            $map[$i] = $session;
            $sections[$i]['session'] = $session;
        }
        return $map;
    }

    private function parseQuestions(string $html): array
    {
        $pattern = '/<div style="background-color: #[0-9A-Fa-f]{6}; border: 1px solid #CBD5E1; border-radius: 5px; padding: 10px 12px; margin-bottom: 12px; page-break-inside: avoid;">'
            .'\s*(<p style="font-weight: 700; margin-bottom: 4px;">Q(\d+)\.\s*(.*?)<\/p>\s*<ul[^>]*>(.*?)<\/ul>\s*(?:<div[^>]*>|<p[^>]*>)?.*?<strong>Correct Answer:\s*([A-D])\.?<\/strong>)(.*?)<\/div>/s';
        preg_match_all($pattern, $html, $m, PREG_OFFSET_CAPTURE);
        $questions = [];
        foreach ($m[0] as $i => $full) {
            $ul = $m[4][$i][0];
            $options = $this->parseOptions($ul);
            $questions[] = [
                'offset' => $full[1],
                'number' => (int) $m[2][$i][0],
                'stem' => $this->clean($m[3][$i][0]),
                'options' => $options,
                'answer' => $m[5][$i][0],
                'explanation' => $this->clean($m[6][$i][0]),
            ];
        }
        return $questions;
    }

    private function parseOptions(string $ul): array
    {
        preg_match_all('/<li>([A-D])\.\s*(.*?)<\/li>/s', $ul, $m);
        $options = [];
        foreach ($m[1] as $i => $key) {
            $options[] = ['key' => $key, 'text' => $this->clean($m[2][$i])];
        }
        return $options;
    }

    private function groupBySession(array $questions, array $sections, array $sessionMap, string $html): array
    {
        $bySession = [];
        foreach ($sessionMap as $sectionIndex => $session) {
            $bySession[$session]['questions'] = [];
        }
        foreach ($sessionMap as $sectionIndex => $session) {
            $start = $sections[$sectionIndex]['offset'];
            $end = ($sectionIndex + 1 < count($sections)) ? $sections[$sectionIndex + 1]['offset'] : strlen($html);
            $bySession[$session]['range'] = ['start' => $start, 'end' => $end];
        }
        foreach ($questions as $q) {
            $owner = null;
            foreach ($sessionMap as $sectionIndex => $session) {
                if ($sections[$sectionIndex]['offset'] <= $q['offset']) {
                    $owner = $session;
                } else {
                    break;
                }
            }
            if ($owner === null) {
                continue;
            }
            $bySession[$owner]['questions'][] = $q;
        }
        return $bySession;
    }

    private function buildSession(int $number, array $data, array $sections, array $sessionMap, string $html, array &$warnings): array
    {
        $phase = $number <= 15 ? 1 : ($number <= 30 ? 2 : ($number <= 39 ? 3 : 4));

        $range = $data['range'];
        $chunk = substr($html, $range['start'], $range['end'] - $range['start']);

        $titles = collect($sections)->whereNotNull('session')->filter(fn ($s) => $s['session'] === $number)->pluck('title')->all();

        $title = self::TITLE_OVERRIDES[$number] ?? null;
        if ($title === null) {
            $first = collect($titles)->first(fn ($t) => (bool) preg_match('/^'.$number.'\.1\b/', $t))
                ?? collect($titles)->first(fn ($t) => (bool) preg_match('/Hour 1:/', $t));
            if ($first !== null) {
                $title = trim(preg_replace('/^\d+\.\d+[a-z]?\s*Hour[^:]*:\s*/', '', $first));
            }
        }
        $title = $title ?: "Session {$number}";

        $topics = [];
        preg_match_all('/<h3[^>]*>(.*?)<\/h3>/s', $chunk, $h3);
        foreach ($h3[1] as $topicHtml) {
            $clean = $this->clean($topicHtml);
            if ($clean !== '' && ! str_starts_with($clean, 'Session ') && ! in_array($clean, $topics, true)) {
                $topics[] = $clean;
            }
        }

        $drills = $this->parseDrills($chunk);

        $items = $data['questions'] ?? [];
        $unique = [];
        $seen = [];
        foreach ($items as $q) {
            $signature = $q['stem'];
            if (isset($seen[$signature])) {
                $warnings[] = "Session {$number}: duplicate stem skipped (Q{$q['number']})";
                continue;
            }
            $seen[$signature] = true;
            $unique[] = $q;
        }
        usort($unique, fn ($a, $b) => $a['number'] <=> $b['number'] ?: ($a['offset'] <=> $b['offset']));

        foreach ($unique as $q) {
            if (count($q['options']) !== 4) {
                $warnings[] = "Session {$number} Q{$q['number']}: expected 4 options, got ".count($q['options']);
            }
            $keys = array_column($q['options'], 'key');
            if (! in_array($q['answer'], $keys, true)) {
                $warnings[] = "Session {$number} Q{$q['number']}: correct answer {$q['answer']} not among option keys";
            }
        }

        $pool = array_map(function ($q) use ($number) {
            return [
                'id' => sprintf('cissp-%02d-q%03d', $number, $q['number']),
                'number' => $q['number'],
                'stem' => $q['stem'],
                'options' => $q['options'],
                'answer' => $q['answer'],
                'explanation' => $q['explanation'],
            ];
        }, $unique);

        $assessmentType = $this->assessmentType($titles);
        $poolSize = count($pool);

        return [
            'number' => $number,
            'phase' => $phase,
            'title' => $title,
            'domain' => self::DOMAINS[$number] ?? 'Security and Risk Management',
            'bloom_level' => self::BLOOM_BY_PHASE[$phase],
            'description' => "Session {$number} of the Hexadigitall CISSP 45-day preparation curriculum: {$title}.",
            'assessment_type' => $assessmentType,
            'assessment_title' => $this->assessmentTitle($assessmentType, $titles),
            'pool_size' => $poolSize,
            'topics' => $topics,
            'drills' => $drills,
            'questions' => $pool,
        ];
    }

    private function parseDrills(string $chunk): array
    {
        $drills = [];
        if (preg_match('/<div class="callout callout-drill">(.*?)<\/ol>\s*<\/div>/s', $chunk, $m)) {
            preg_match_all('/<li>(.*?)<\/li>/s', $m[1], $li);
            foreach ($li[1] as $item) {
                if (preg_match('/<strong>Question:<\/strong>\s*(.*?)(?:<br\s*\/?\s*>)?\s*<em>Answer:<\/em>\s*(.*?)$/s', $item, $q)) {
                    $qtext = $this->clean($q[1]);
                    $atext = $this->clean($q[2]);
                    if ($qtext !== '' && $atext !== '') {
                        $drills[] = ['q' => $qtext, 'a' => $atext];
                    }
                }
            }
        }
        return $drills;
    }

    private function assessmentTitle(string $type, array $titles): string
    {
        $pick = collect($titles)->first(function ($t) {
            return str_contains($t, 'CAT Practice Questions') || str_contains($t, 'Simulated CAT')
                || str_contains($t, 'Mock Exam') || str_contains($t, 'Benchmark Diagnostic') || str_contains($t, '50-Question');
        });
        $pick ??= 'Session assessment';
        return trim(preg_replace('/^([\d.]+)*\s*Hour[^:]*:\s*/', '', $pick));
    }

    private function assessmentType(array $titles): string
    {
        $joined = strtolower(implode(' ', $titles));
        if (str_contains($joined, 'benchmark diagnostic exam')) return 'benchmark';
        if (str_contains($joined, 'simulated cat exam section')) return 'simulated_cat';
        if (str_contains($joined, 'full-length cat simulation mock exam #2')) return 'mock_exam_2';
        if (str_contains($joined, 'full-length cat simulation mock exam #1')) return 'mock_exam_1';
        if (str_contains($joined, 'cat practice questions')) return 'drill';
        return 'none';
    }

    private function clean(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
}