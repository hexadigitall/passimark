<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\{PassimarkCertificationTrack, PassimarkSession, PassimarkExam, PassimarkProgress, User};

/**
 * Worldwide Passimark Catalog — 17 flagship certifications (approved v4.0 source).
 *
 * Methodology (per the approved spec):
 *  1. Lesson: 25 CAT @ User's Pace
 *  2. Phase (cluster of lessons): 50-75 CAT @UP, must pass to unlock next phase
 *  3. Domain (cluster of phases): 50-75 CAT @UP pressure
 *  4. Mocks: 2-3 full CAT, timer, pressure, Pearson VUE chrome, varied counts
 *  5. Final: 100% real spec — real #Q, real time, full experience
 *
 * The catalog array below is faithful to WorldwidePassimarkCatalogSeeder.php from the
 * approved v4.0 FINAL package. Sessions are written into the v4 phase_type ladder model
 * (cert|lesson|phase|domain|mock|final), with phase-1 first-lesson gating and pass-gated
 * auto-unlock (PassimarkController::autoUnlockNext). The legacy CISSP demo/bundle tracks
 * (region = NULL) are left untouched.
 */
class WorldwidePassimarkCatalogSeeder extends Seeder
{
    public const CATALOG_JSON = 'seeders/data/worldwide/worldwide-17-cert-catalog.json';

    public function run(): void
    {
        DB::disableQueryLog();
        DB::transaction(function () {
            $catalog = $this->getWorldwideCatalog();
            $stats = $this->seedCatalog($catalog);
            if ($this->command) {
                $this->command->info(sprintf(
                    'Worldwide catalog seeded: %d cert tracks, %d sessions, %d exams across %d regions.',
                    $stats['tracks'], $stats['sessions'], $stats['exams'], $stats['regions']
                ));
            }
        });
    }

    /**
     * Build the catalog for a subset of regions/certs (used by feature tests or CLI flags).
     * Returns [tracks, sessions, exams, regions].
     */
    public function seedCatalog(array $catalog): array
    {
        $regionFilter = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('SEED_REGIONS', '')))));
        $certFilter = array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('SEED_CERTS', '')))));

        $trackCount = $sessionCount = $examCount = 0;
        $regions = [];
        $number = 1;

        foreach ($catalog as $region => $certs) {
            if ($regionFilter && ! in_array(strtolower($region), $regionFilter, true)) {
                continue;
            }
            foreach ($certs as $certCode => $cert) {
                if ($certFilter && ! in_array(strtolower($certCode), $certFilter, true)) {
                    continue;
                }
                $trackCount++;
                $regions[$region] = true;
                $track = PassimarkCertificationTrack::updateOrCreate(
                    ['slug' => Str::slug($certCode)],
                    [
                        'title' => $cert['name'],
                        'description' => $cert['desc'],
                        'region' => $region,
                        'is_active' => true,
                    ]
                );

                PassimarkSession::where('certification_track_id', $track->id)->delete();

                $order = 1;
                $container = $this->session($track, $certCode, $number++, $order, [
                    'phase' => 0,
                    'phase_type' => 'cert',
                    'title' => "CERT • {$cert['name']} ({$certCode})",
                    'description' => $cert['desc'] . " | Methodology: 25Q Lesson UP -> 50-75Q Phase UP -> 50-75Q Domain UP -> {$cert['mock_count']} Mocks Full CAT -> Final {$cert['final_q']}Q Real",
                    'domain' => "{$region} | {$certCode}",
                    'is_open' => true,
                    'pass_score' => $cert['pass_score'],
                    'time_minutes' => $cert['final_time'],
                    'questions_target' => $cert['final_q'],
                ]);
                $examCount += $this->createExams($container, $cert['final_q'], $cert['final_time']);

                foreach ($cert['phases'] as $phaseIdx => $phase) {
                    $phaseNum = $phaseIdx + 1;

                    foreach ($phase['lessons'] as $lessonIdx => $lessonTitle) {
                        $lesson = $this->session($track, $certCode, $number++, $order, [
                            'phase' => $phaseNum,
                            'phase_type' => 'lesson',
                            'title' => "LESSON • {$certCode} P{$phaseNum}L" . ($lessonIdx + 1) . " • {$lessonTitle} [25Q @UP]",
                            'description' => "Lesson CAT: 25 Questions @ User's Pace. Solid = Dotted Becoming Solid. Part of Phase: {$phase['name']}",
                            'domain' => "{$region} | {$certCode} | Phase {$phaseNum} | {$phase['name']}",
                            'is_open' => $phaseNum === 1 && $lessonIdx === 0,
                            'pass_score' => 70,
                            'time_minutes' => 0,
                            'questions_target' => 25,
                        ]);
                        $examCount += $this->createExams($lesson, 25, 0);
                    }

                    $phaseAssessment = $this->session($track, $certCode, $number++, $order, [
                        'phase' => $phaseNum,
                        'phase_type' => 'phase',
                        'title' => "PHASE CAT • {$certCode} Phase {$phaseNum}: {$phase['name']} [{$phase['phase_q']}Q @UP]",
                        'description' => 'Phase Assessment: Cluster of ' . count($phase['lessons']) . ' lessons. ' . $phase['phase_q'] . ' CAT @ User-Paced (soft timer). Must pass to unlock next phase.',
                        'domain' => "{$region} | {$certCode} | PHASE ASSESSMENT | {$phase['name']}",
                        'is_open' => false,
                        'pass_score' => 70,
                        'time_minutes' => $phase['phase_time'],
                        'questions_target' => $phase['phase_q'],
                    ]);
                    $examCount += $this->createExams($phaseAssessment, $phase['phase_q'], $phase['phase_time']);
                }

                foreach (array_chunk($cert['phases'], 2) as $dIdx => $dPhases) {
                    $domainNames = implode(' + ', array_column($dPhases, 'name'));
                    $domain = $this->session($track, $certCode, $number++, $order, [
                        'phase' => 90 + $dIdx,
                        'phase_type' => 'domain',
                        'title' => "DOMAIN CAT • {$certCode} Domain " . ($dIdx + 1) . ": {$domainNames} [75Q @UP Pressure]",
                        'description' => 'Domain Assessment: Cluster of ' . count($dPhases) . ' phases. 75 CAT @ UP with pressure building. Theta moves here.',
                        'domain' => "{$region} | {$certCode} | DOMAIN | {$domainNames}",
                        'is_open' => false,
                        'pass_score' => 72,
                        'time_minutes' => 90,
                        'questions_target' => 75,
                    ]);
                    $examCount += $this->createExams($domain, 75, 90);
                }

                for ($m = 1; $m <= $cert['mock_count']; $m++) {
                    $mockQ = match ($m) {
                        1 => (int) round($cert['final_q'] * 0.7),
                        2 => $cert['final_q'],
                        3 => (int) round($cert['final_q'] * 1.2),
                        default => $cert['final_q'],
                    };
                    $mockTime = (int) round($cert['final_time'] * ($mockQ / $cert['final_q']));
                    $mock = $this->session($track, $certCode, $number++, $order, [
                        'phase' => 95,
                        'phase_type' => 'mock',
                        'title' => "MOCK {$m} • {$certCode} Full CAT Pearson VUE [{$mockQ}Q, {$mockTime}min]",
                        'description' => "Mock {$m}: Full CAT Experience, Timer, Pressure, Pearson VUE UI, Flag/Review, Question Palette. Varied counts for confidence -> real -> stretch.",
                        'domain' => "{$region} | {$certCode} | MOCK {$m}",
                        'is_open' => false,
                        'pass_score' => $cert['pass_score'],
                        'time_minutes' => $mockTime,
                        'questions_target' => $mockQ,
                    ]);
                    $examCount += $this->createExams($mock, $mockQ, $mockTime);
                }

                $final = $this->session($track, $certCode, $number++, $order, [
                    'phase' => 100,
                    'phase_type' => 'final',
                    'title' => "FINAL • {$certCode} Real Exam Spec [{$cert['final_q']}Q, {$cert['final_time']}min] - Verifiable Certificate",
                    'description' => "Final: 100% Real Exam Spec - Real #Q ({$cert['final_q']}), Real Time ({$cert['final_time']}min), Real Breaks, Full Pearson VUE Experience, No Hints. Generates Passimark Certificate with Theta, QR, Percentile.",
                    'domain' => "{$region} | {$certCode} | FINAL EXAM",
                    'is_open' => false,
                    'pass_score' => $cert['pass_score'],
                    'time_minutes' => $cert['final_time'],
                    'questions_target' => $cert['final_q'],
                ]);
                $examCount += $this->createExams($final, $cert['final_q'], $cert['final_time'], true);

                $sessionCount += $order - 1;
            }
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

    private function session(PassimarkCertificationTrack $track, string $certCode, int $number, int &$order, array $data): PassimarkSession
    {
        return PassimarkSession::create([
            'certification_track_id' => $track->id,
            'cert_slug' => Str::slug($certCode),
            'number' => $number,
            'phase' => $data['phase'],
            'phase_type' => $data['phase_type'],
            'title' => $data['title'],
            'description' => $data['description'],
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

    /**
     * Each session ships the three exam modes; adaptive (cat) gets 1.5x the session time,
     * practice is user-paced (0), timed is the session time. Finals flag their exams.
     */
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

    public function getWorldwideCatalog(): array
    {
        return [
            'USA-IT-CLOUD' => [
                'AWS-SAA' => [
                    'name' => 'AWS Solutions Architect Associate SAA-C03',
                    'desc' => 'AWS Certified Solutions Architect - Most demanded globally',
                    'pass_score' => 72, 'final_q' => 65, 'final_time' => 130, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'IAM & Security Foundation', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['IAM Users Groups Policies', 'IAM Roles & Federation', 'KMS & Secrets Manager', 'Security Best Practices']],
                        ['name' => 'EC2 & Compute', 'phase_q' => 65, 'phase_time' => 80, 'lessons' => ['EC2 Instances Types', 'Auto Scaling & ELB', 'Lambda & Serverless', 'Elastic Beanstalk']],
                        ['name' => 'S3 & Storage', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['S3 Buckets & Policies', 'S3 Versioning Replication', 'EBS & EFS', 'Storage Gateway']],
                        ['name' => 'VPC & Networking', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['VPC Subnets Route Tables', 'NAT Gateway & VPN', 'Route53 & DNS', 'CloudFront & Direct Connect']],
                        ['name' => 'HA Architecture & Databases', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['RDS & Aurora', 'DynamoDB & ElastiCache', 'CloudFormation & HA Design', 'Well-Architected Framework']],
                    ],
                ],
                'AWS-SAP' => [
                    'name' => 'AWS Solutions Architect Professional SAP-C02',
                    'desc' => 'AWS Professional Level',
                    'pass_score' => 75, 'final_q' => 75, 'final_time' => 180, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Advanced Organization & Multi-Account', 'phase_q' => 70, 'phase_time' => 90, 'lessons' => ['Organizations SCPs', 'Control Tower', 'Landing Zone', 'Cross-Account']],
                        ['name' => 'Hybrid & Migration', 'phase_q' => 70, 'phase_time' => 90, 'lessons' => ['Migration Strategies', 'DMS & Snow Family', 'Hybrid Networking', 'Disaster Recovery']],
                        ['name' => 'Enterprise Architecting', 'phase_q' => 75, 'phase_time' => 100, 'lessons' => ['Enterprise Patterns', 'Cost Optimization', 'Security at Scale', 'Final Architecting']],
                    ],
                ],
                'AZ-104' => [
                    'name' => 'Microsoft Azure Administrator AZ-104',
                    'desc' => 'Azure Admin Associate - Global',
                    'pass_score' => 70, 'final_q' => 60, 'final_time' => 120, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Identity & Governance', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['Azure AD & RBAC', 'Subscriptions Governance', 'Storage Management', 'Resource Management']],
                        ['name' => 'Compute & Networking', 'phase_q' => 65, 'phase_time' => 80, 'lessons' => ['VMs & App Services', 'Virtual Networking', 'Intersite Connectivity', 'Network Traffic']],
                    ],
                ],
                'CCNA' => [
                    'name' => 'Cisco CCNA 200-301',
                    'desc' => 'Cisco Certified Network Associate - Worldwide',
                    'pass_score' => 82, 'final_q' => 120, 'final_time' => 120, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Network Fundamentals', 'phase_q' => 70, 'phase_time' => 80, 'lessons' => ['OSI & TCP/IP', 'Cabling & Topologies', 'IPv4 & IPv6', 'Wireless']],
                        ['name' => 'IP Connectivity & Services', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['Routing Concepts', 'Static & OSPF', 'NAT & NTP', 'DHCP & DNS']],
                        ['name' => 'Security & Automation', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['ACLs & Security', 'Automation & Programmability', 'Final Labs']],
                    ],
                ],
            ],
            'USA-IT-SECURITY' => [
                'SEC+' => [
                    'name' => 'CompTIA Security+ SY0-701',
                    'desc' => 'Global entry cybersecurity',
                    'pass_score' => 75, 'final_q' => 90, 'final_time' => 90, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Threats & Vulnerabilities', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Threat Actors', 'Social Engineering', 'Malware Types', 'Vulnerability Scanning']],
                        ['name' => 'Architecture & Design', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['Security Concepts', 'Secure Protocols', 'Cloud Security', 'Resilience']],
                        ['name' => 'Implementation & Operations', 'phase_q' => 65, 'phase_time' => 80, 'lessons' => ['Identity & Access', 'Cryptography', 'Wireless & Endpoint', 'Incident Response']],
                    ],
                ],
                'CISSP' => [
                    'name' => 'ISC2 CISSP - Gold Standard',
                    'desc' => 'CISSP 8 Domains - 150Q adaptive real is 125-175Q',
                    'pass_score' => 70, 'final_q' => 150, 'final_time' => 180, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Security & Risk Management', 'phase_q' => 75, 'phase_time' => 90, 'lessons' => ['Governance Frameworks', 'Risk Assessment', 'BIA & BCP', 'Legal Compliance']],
                        ['name' => 'Asset Security & Architecture', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['Data Classification', 'Crypto Fundamentals', 'Security Models', 'Physical Security']],
                        ['name' => 'Network & IAM', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['Network Security', 'Access Control', 'Federation & PAM', 'Assessment Testing']],
                        ['name' => 'Operations & Software', 'phase_q' => 70, 'phase_time' => 85, 'lessons' => ['SOC & IR', 'Disaster Recovery', 'SDLC & DevSecOps', 'Third-Party Risk']],
                    ],
                ],
            ],
            'USA-PMP' => [
                'PMP' => [
                    'name' => 'PMI PMP - Project Management Professional',
                    'desc' => 'PMP 180Q - Global gold standard',
                    'pass_score' => 65, 'final_q' => 180, 'final_time' => 230, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'People - Team Leadership', 'phase_q' => 70, 'phase_time' => 90, 'lessons' => ['Build Team', 'Define Ground Rules', 'Negotiate Agreements', 'Empower Team']],
                        ['name' => 'Process - Technical PM', 'phase_q' => 75, 'phase_time' => 100, 'lessons' => ['Execute with Urgency', 'Manage Communications', 'Assess Risks', 'Plan Schedule Budget']],
                        ['name' => 'Business Environment', 'phase_q' => 60, 'phase_time' => 80, 'lessons' => ['Compliance', 'Benefits & Value', 'Org Change', 'Final PM Mindset']],
                    ],
                ],
            ],
            'UK-EU-FINANCE' => [
                'ACCA-F1-F4' => [
                    'name' => 'ACCA Applied Knowledge F1-F4',
                    'desc' => 'ACCA Global - UK based worldwide',
                    'pass_score' => 50, 'final_q' => 50, 'final_time' => 120, 'mock_count' => 2,
                    'phases' => [
                        ['name' => 'Business & Technology BT/F1', 'phase_q' => 50, 'phase_time' => 60, 'lessons' => ['Organizations', 'Business Environment', 'Functions', 'Governance Ethics']],
                        ['name' => 'Management Accounting MA/F2', 'phase_q' => 50, 'phase_time' => 60, 'lessons' => ['Costing', 'Budgeting', 'Decision Making', 'Variance']],
                    ],
                ],
                'CFA-L1' => [
                    'name' => 'CFA Level I',
                    'desc' => 'Chartered Financial Analyst - Global finance',
                    'pass_score' => 70, 'final_q' => 180, 'final_time' => 270, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Quant & Economics', 'phase_q' => 70, 'phase_time' => 90, 'lessons' => ['Quant Methods', 'Economics', 'Financial Statement', 'Corporate Issuers']],
                        ['name' => 'Equity & Fixed Income & Derivatives', 'phase_q' => 75, 'phase_time' => 100, 'lessons' => ['Equity', 'Fixed Income', 'Derivatives', 'Alternative']],
                        ['name' => 'Portfolio & Ethics', 'phase_q' => 60, 'phase_time' => 80, 'lessons' => ['Portfolio Management', 'Ethics GIPS', 'Final Review']],
                    ],
                ],
            ],
            'AFRICA-NG' => [
                'JAMB-SCI' => [
                    'name' => 'JAMB UTME Science Bundle 2026',
                    'desc' => 'Nigeria JAMB Science - English Maths Physics Chemistry',
                    'pass_score' => 70, 'final_q' => 180, 'final_time' => 120, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Use of English', 'phase_q' => 60, 'phase_time' => 45, 'lessons' => ['Comprehension', 'Lexis Structure', 'Oral Forms', 'Writing']],
                        ['name' => 'Mathematics', 'phase_q' => 60, 'phase_time' => 60, 'lessons' => ['Numbers', 'Algebra', 'Geometry Trig', 'Calculus Stats']],
                        ['name' => 'Physics & Chemistry Core', 'phase_q' => 70, 'phase_time' => 70, 'lessons' => ['Mechanics', 'Waves Optics', 'Physical Chemistry', 'Organic Inorganic']],
                    ],
                ],
                'WAEC-SCI' => [
                    'name' => 'WAEC SSCE Science Complete',
                    'desc' => 'West Africa Examination Council',
                    'pass_score' => 50, 'final_q' => 60, 'final_time' => 180, 'mock_count' => 2,
                    'phases' => [
                        ['name' => 'Core Sciences Theory', 'phase_q' => 50, 'phase_time' => 90, 'lessons' => ['Physics Theory', 'Chemistry Theory', 'Biology Theory', 'Practical Alt']],
                    ],
                ],
                'ICAN-SKILLS' => [
                    'name' => 'ICAN Skills Level - Nigeria Chartered Accountant',
                    'desc' => 'ICAN Skills - 6 Papers',
                    'pass_score' => 50, 'final_q' => 80, 'final_time' => 180, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Financial Reporting & Audit', 'phase_q' => 70, 'phase_time' => 90, 'lessons' => ['Financial Reporting', 'Audit Assurance', 'Performance Management', 'Public Sector']],
                        ['name' => 'Taxation & Business', 'phase_q' => 60, 'phase_time' => 80, 'lessons' => ['Taxation', 'Business Law']],
                    ],
                ],
            ],
            'GLOBAL-ACADEMIC' => [
                'SAT' => [
                    'name' => 'SAT Digital 2026',
                    'desc' => 'USA College Entrance - Global',
                    'pass_score' => 70, 'final_q' => 98, 'final_time' => 134, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Reading & Writing', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Information Ideas', 'Craft Structure', 'Expression Standard English', 'Writing']],
                        ['name' => 'Math', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Algebra', 'Advanced Math', 'Problem Solving Data', 'Geometry Trig']],
                    ],
                ],
                'IELTS' => [
                    'name' => 'IELTS Academic & General',
                    'desc' => 'International English - UK, Canada, Australia global',
                    'pass_score' => 70, 'final_q' => 40, 'final_time' => 165, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Listening & Reading', 'phase_q' => 50, 'phase_time' => 60, 'lessons' => ['Listening Section 1-4', 'Reading Academic', 'Reading General', 'Strategies']],
                        ['name' => 'Writing & Speaking', 'phase_q' => 50, 'phase_time' => 60, 'lessons' => ['Writing Task 1', 'Writing Task 2', 'Speaking Part 1-3', 'Fluency Coherence']],
                    ],
                ],
                'GRE' => [
                    'name' => 'GRE General Test',
                    'desc' => 'Graduate Record Examination - Global Masters',
                    'pass_score' => 70, 'final_q' => 82, 'final_time' => 158, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Verbal Reasoning', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Reading Comprehension', 'Text Completion', 'Sentence Equivalence', 'Vocab']],
                        ['name' => 'Quantitative Reasoning', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['Arithmetic', 'Algebra', 'Geometry', 'Data Analysis']],
                        ['name' => 'Analytical Writing', 'phase_q' => 50, 'phase_time' => 60, 'lessons' => ['Issue Task', 'Argument Task']],
                    ],
                ],
                'NCLEX-RN' => [
                    'name' => 'NCLEX-RN - US Nursing Licensing - Adaptive is perfect',
                    'desc' => 'NCLEX is CAT 75-145Q - Native to Passimark engine',
                    'pass_score' => 70, 'final_q' => 145, 'final_time' => 300, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Management of Care', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['Advance Directives', 'Advocacy', 'Case Management', 'Informed Consent']],
                        ['name' => 'Safety & Infection Control', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['Accident Prevention', 'Infection Control', 'Emergency Response', 'Hazardous']],
                        ['name' => 'Health Promotion & Psychosocial', 'phase_q' => 60, 'phase_time' => 75, 'lessons' => ['Growth Development', 'Aging', 'Stress Coping', 'Support Systems']],
                        ['name' => 'Physiological Integrity', 'phase_q' => 75, 'phase_time' => 90, 'lessons' => ['Basic Care', 'Pharmacology', 'Reduction Risk', 'Physiological Adaptation']],
                    ],
                ],
            ],
            'APAC-IN' => [
                'JEE-MAIN' => [
                    'name' => 'JEE Main - India Engineering Entrance - 1.2M candidates',
                    'desc' => 'India massive market',
                    'pass_score' => 70, 'final_q' => 90, 'final_time' => 180, 'mock_count' => 3,
                    'phases' => [
                        ['name' => 'Physics', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Mechanics', 'Electrodynamics', 'Modern Physics', 'Optics Waves']],
                        ['name' => 'Chemistry', 'phase_q' => 60, 'phase_time' => 70, 'lessons' => ['Physical', 'Organic', 'Inorganic', 'Practical']],
                        ['name' => 'Mathematics', 'phase_q' => 70, 'phase_time' => 80, 'lessons' => ['Algebra', 'Calculus', 'Coordinate Geometry', 'Trigonometry']],
                    ],
                ],
            ],
        ];
    }
}