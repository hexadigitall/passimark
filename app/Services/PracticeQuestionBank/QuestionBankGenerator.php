<?php

namespace App\Services\PracticeQuestionBank;

/**
 * Deterministic port of the "Passimark Original Question Bank Generator"
 * (client-side catalog, dispatch + scenario templates). Uses a seeded mulberry32
 * PRNG so that, for the same seed, question banks are byte-for-byte reproducible.
 *
 * Catalogue entries: { code, name, vendor, refDoc, domains: [{name, weight, objectives[]}] }.
 * A generated item carries: { objective_reference, content, options[4], difficulty,
 * discrimination, guessing, domain, bloom_level, explanation, reference }.
 *
 * Dispatch matches the original S1(): SAA-C03/AWS->y1, PMP/PMI->m1, CISSP/ISC2->v1,
 * CCNA/Cisco->yu (reference/domain forced), SY0-701/CompTIA->yu, JAMB->g1, SAT->w1,
 * NCLEX-RN->yu, default->yu. CSS certs fall into generic yu or one of the air hosts.
 */
final class QuestionBankGenerator
{
    public const CATALOG_JSON = 'seeders/data/original-bank/catalog.json';

    private const COMPANIES = ['PaySwift Ltd', 'NaijaMart', 'Lagos Logistics Co.', 'AeroTech NG', 'FinEdge Africa', 'Zaria Health Systems', 'Kano AgriTech'];

    private const STACKS = ['Node.js microservices', 'Python data pipeline', 'Serverless API Gateway + Lambda', 'EKS cluster', 'ECS Fargate workload'];

    private const BLOOMS = ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate'];

    /** @var array<string,array<string,mixed>>|null */
    private ?array $catalog = null;

    public function __construct(private readonly SeededRandom $rand)
    {
    }

    public static function withSeed(int $seed): self
    {
        return new self(new SeededRandom($seed));
    }

    /** @return array<string,array<string,mixed>> catalog keyed by cert code */
    public function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }
        $path = database_path(self::CATALOG_JSON);
        $items = json_decode((string) file_get_contents($path), true);
        $this->catalog = [];
        foreach ($items as $item) {
            $this->catalog[$item['code']] = $item;
        }
        return $this->catalog;
    }

    public function cert(string $code): ?array
    {
        return $this->catalog()[$code] ?? null;
    }

    /**
     * Generate one self-contained question for a cert entry.
     *
     * @param array<string,mixed> $cert catalog entry {code,name,vendor,refDoc,domains}
     */
    public function question(array $cert, int $index): array
    {
        $total = array_sum(array_column($cert['domains'], 'weight'));
        $roll = $this->rand->next() * $total;
        $domain = $cert['domains'][0];
        foreach ($cert['domains'] as $candidate) {
            if ($roll < $candidate['weight']) {
                $domain = $candidate;
                break;
            }
            $roll -= $candidate['weight'];
        }
        $objective = $this->rand->pick($domain['objectives']);

        return match (true) {
            $cert['code'] === 'SAA-C03' || $cert['vendor'] === 'AWS' => $this->y1($index, $domain, $objective, $cert),
            $cert['code'] === 'PMP' || $cert['vendor'] === 'PMI' => $this->m1($index, $domain, $objective, $cert),
            $cert['code'] === 'CISSP' || $cert['vendor'] === 'ISC2' => $this->v1($index, $domain, $objective, $cert),
            $cert['code'] === 'CCNA' || $cert['vendor'] === 'Cisco' => array_merge(
                $this->yu($index, $domain, $objective, $cert),
                ['reference' => $cert['refDoc'], 'domain' => $domain['name']]
            ),
            $cert['code'] === 'SY0-701' || $cert['vendor'] === 'CompTIA' ||
            $cert['code'] === 'NCLEX-RN' => $this->yu($index, $domain, $objective, $cert),
            $cert['code'] === 'JAMB' => $this->g1($index, $domain, $objective, $cert),
            $cert['code'] === 'SAT' => $this->w1($index, $domain, $objective, $cert),
            default => $this->yu($index, $domain, $objective, $cert),
        };
    }

    /** AWS Well-Architected scenario bank (y1). */
    private function y1(int $index, array $domain, string $objective, array $cert): array
    {
        $l = $this->rand->pick(self::COMPANIES);
        $u = $this->rand->pick(self::STACKS);
        $o = $this->rand->pick(['15 min', '30 min', '1 hour', '5 min']);
        $i = $this->rand->pick(['5 min', '1 min', '0', '15 min']);
        $candidates = [
            [
                'q' => "{$l} runs a {$u} that must survive AZ failure with RTO {$o} and RPO {$i}. Current architecture uses single-AZ EC2 with local storage. Which combination meets resilience and cost goals? (Original scenario " . ($index + 1) . ')',
                'opts' => [
                    "Deploy across 2 AZs with ALB, Auto Scaling Group min 2, RDS Multi-AZ with automated backups every {$i}",
                    'Use single EC2 instance with EBS snapshot every hour and manual failover',
                    'Place all components in one AZ with larger instance type to improve MTTR',
                    'Use S3 static hosting for dynamic API and restore from Glacier for failover',
                ],
                'correct' => 0,
                'exp' => "Multi-AZ ALB+ASG and RDS Multi-AZ provides automated AZ failover meeting RTO {$o}/RPO {$i}. Reference: {$cert['refDoc']} - Reliability Pillar - Design for failure, automatic recovery.",
            ],
            [
                'q' => "A workload at {$l} needs to handle 10k TPS burst. The team proposes {$this->rand->pick(['vertical scaling only', 'single threaded queue', 'synchronous calls to third party'])}. Which high-performing design aligns with {$objective}?",
                'opts' => [
                    'Use SQS to decouple, Lambda concurrency with reserved concurrency, DynamoDB on-demand with DAX caching',
                    'Increase EC2 instance to 64xlarge and keep synchronous blocking calls',
                    'Store all data in single RDS table without indexes for simplicity',
                    'Use EC2 instance store for session state to improve performance',
                ],
                'correct' => 0,
                'exp' => "Decoupling with SQS and elastic compute with caching follows Well-Architected Performance Pillar. Reference: {$cert['refDoc']} - Performance Efficiency.",
            ],
            [
                'q' => "{$l} must secure {$u} handling PII. Requirement: least privilege, KMS encryption, and private subnets. Which secure architecture meets {$objective}? (Scenario " . ($index + 1) . ')',
                'opts' => [
                    'VPC with private subnets, NAT Gateway, IAM roles with least privilege, KMS CMK for S3/RDS, WAF on ALB, Secrets Manager',
                    'Public subnets for all tiers with security groups open to 0.0.0.0/0 and IAM admin role for app',
                    'Store KMS keys in code repository for easy rotation and use root account for app access',
                    'Disable CloudTrail to reduce log storage cost and use hardcoded access keys',
                ],
                'correct' => 0,
                'exp' => "Private subnets, least privilege IAM, KMS, Secrets Manager, WAF aligns with Security Pillar. Ref: {$cert['refDoc']}.",
            ],
        ];
        $c = $this->rand->pick($candidates);
        $h = $this->mapOptions($c['opts'], $c['correct']);

        return $this->finish(
            "{$cert['code']} Obj {$this->rand->int(1, 4)}.{$this->rand->int(1, 4)} - {$objective}",
            $c['q'], $h, $domain['name'], $c['exp'], $cert['refDoc'],
            $this->rand->range(-1.5, 2), $this->rand->range(0.9, 1.7),
            $this->rand->pick(array_slice(self::BLOOMS, 2))
        );
    }

    /** PMP/PMBOK 7 scenario bank (m1). */
    private function m1(int $index, array $domain, string $objective, array $cert): array
    {
        $candidates = [
            [
                'q' => 'A project sponsor requests a ' . $this->rand->pick(['scope change adding 20% budget', 'schedule compression request', 'new regulatory requirement', 'vendor delivery delay']) . ' that adds business value but impacts baseline. As PM, what is your FIRST action per PMBOK 7 for objective "' . $objective . '"? (Original ' . ($index + 1) . ')',
                'opts' => [
                    'Assess impact and submit through Perform Integrated Change Control with business case and risk analysis',
                    'Immediately approve to satisfy sponsor',
                    'Reject because scope is baselined',
                    'Inform team to start work and formalize later',
                ],
                'correct' => 0,
                'exp' => "PMBOK 7 Planning and Measurement: All changes go through integrated change control after impact analysis. Ref: {$cert['refDoc']} - Change Control.",
            ],
            [
                'q' => "During iteration review, team velocity dropped 30% due to interpersonal conflict. For \"{$objective}\" in People domain, what should PM do?",
                'opts' => [
                    'Facilitate ground rules workshop, use emotional intelligence, and coach team on conflict resolution',
                    'Remove underperforming members immediately',
                    'Escalate to HR and stop project',
                    'Ignore conflict as forming-storming is normal without intervention',
                ],
                'correct' => 0,
                'exp' => "People domain emphasizes team building and emotional intelligence. Ref: {$cert['refDoc']} - Team Performance Domain.",
            ],
        ];
        $o = $this->rand->pick($candidates);
        $i = $this->mapOptions($o['opts'], $o['correct']);

        return $this->finish(
            "{$cert['code']} ECO {$this->rand->int(1, 3)}.{$this->rand->int(1, 6)} - {$objective}",
            $o['q'], $i, $domain['name'], $o['exp'], $cert['refDoc'],
            $this->rand->range(-1, 2.2), $this->rand->range(0.8, 1.8),
            $this->rand->pick(['Apply', 'Analyze', 'Evaluate'])
        );
    }

    /** CISSP/ISC2 domain bank (v1). */
    private function v1(int $index, array $domain, string $objective, array $cert): array
    {
        $candidates = [
            [
                'q' => 'Under ' . $this->rand->pick(['GDPR', 'CCPA', 'HIPAA', 'PCI-DSS']) . ', a data breach affecting EU residents must be reported to supervisory authority within what timeframe? Scenario: ' . $this->rand->pick(self::COMPANIES) . ' detected unauthorized access to ' . $this->rand->pick(['customer PII', 'health records', 'payment cards']) . " affecting 2,400 records. (Original Q" . ($index + 1) . ')',
                'opts' => ['72 hours', '24 hours', '30 days', 'Without undue delay but no specific timeframe in regulation'],
                'correct' => 0,
                'exp' => "GDPR Article 33 requires notification within 72 hours. For CCPA/others, timeframe varies but GDPR is strict. Ref: {$cert['refDoc']} - Legal and Regulatory Compliance. Objective: {$objective}",
            ],
            [
                'q' => "Which principle best supports \"{$objective}\" when designing a zero-trust architecture for {$this->rand->pick(self::COMPANIES)}?",
                'opts' => [
                    'Never trust, always verify with least privilege and continuous authentication',
                    'Trust but verify after initial login',
                    'Perimeter-based security with VPN only',
                    'Implicit trust for internal network',
                ],
                'correct' => 0,
                'exp' => "Zero trust aligns with secure design principles and least privilege. Ref: {$cert['refDoc']} - Security Architecture.",
            ],
        ];
        $i = $this->rand->pick($candidates);
        $h = $this->mapOptions($i['opts'], $i['correct']);

        return $this->finish(
            "{$cert['code']} Domain {$domain['name']} - {$objective}",
            $i['q'], $h, $domain['name'], $i['exp'], $cert['refDoc'],
            $this->rand->range(-0.5, 2.5), $this->rand->range(1, 1.8),
            $this->rand->pick(['Remember', 'Understand', 'Apply'])
        );
    }

    /** JAMB UTME passage/algebra bank (g1). */
    private function g1(int $index, array $domain, string $objective, array $cert): array
    {
        $passages = [
            "Technology in Nigeria has transformed education in unprecedented ways. In Lagos, Aisha, a secondary school teacher, noticed that her students now access learning materials through mobile phones, even in areas with intermittent electricity. She developed an offline-first learning app called LearnNaija that syncs when connectivity returns. Critics argued that technology would replace teachers, but Aisha argued it amplifies good teaching. Her students' WAEC pass rate improved from 42% to 78% in two years. The challenge remains equitable access, as rural schools still lack devices. Yet, the potential is undeniable: when technology is designed for local constraints, it becomes a bridge, not a barrier.",
            "The ancient city of Kano has long been a center of commerce. Traders from across the Sahel bring leather goods, textiles, and spices to its bustling markets. Recently, young entrepreneurs like Musa have combined this heritage with e-commerce, listing traditional crafts online. This fusion has created new income streams while preserving cultural identity. However, logistics and payment trust remain hurdles. Musa's solution was a cooperative delivery network and escrow payments. His story illustrates how innovation need not erase tradition; it can extend its reach.",
        ];
        $u = $this->rand->pick($passages);
        $passage = substr($u, 0, 220);
        $candidates = [
            [
                'q' => "Read the passage about technology/entrepreneurship in Nigeria (Passage " . ($index + 1) . "): \"{$passage}...\" What is the author's main argument about technology and local context?",
                'opts' => [
                    'Technology is most effective when adapted to local constraints like connectivity and power',
                    'Technology always replaces teachers and traditional markets',
                    'Rural areas cannot benefit from any technology',
                    'WAEC results are irrelevant to technology adoption',
                ],
                'correct' => 0,
                'exp' => "Main idea: adaptation to local constraints. Ref: {$cert['refDoc']} - Comprehension and inference skills.",
            ],
            [
                'q' => 'In the context of JAMB English Lexis, the word "unprecedented" in the passage most nearly means? (Original vocab Q' . ($index + 1) . ')',
                'opts' => ['Never seen or experienced before', 'Previously criticized', 'Slowly developing', 'Financially expensive'],
                'correct' => 0,
                'exp' => 'Vocabulary in context. Unprecedented = never before seen. Ref: JAMB Syllabus - Lexis and Structure.',
            ],
        ];

        // Randomized solving window: 3x+2y=E with x=2y solves to x=k (E multiple of 8).
        $v = $this->rand->int(1, 4);
        $k = $v * 2;
        $E = 3 * $k + 2 * $v;
        $algebra = [
            'q' => "If 3x + 2y = {$E} and x = 2y, what is the value of x? (Original algebra - JAMB Obj {$objective})",
            'opts' => [(string) $k, (string) ($k + 1), (string) ($k + 2), (string) ($k - 1)],
            'correct' => 0,
            'exp' => "Substitute x=2y: 3(2y)+2y = 8y = {$E} => y={$v}, x={$k}. Ref: {$cert['refDoc']} - Algebra.",
        ];

        $chosen = $index % 3 === 2 ? $algebra : $this->rand->pick($candidates);
        $p = $this->mapOptions($chosen['opts'], $chosen['correct']);

        return $this->finish(
            "JAMB {$domain['name']} - {$objective} - Q" . ($index + 1),
            $chosen['q'], $p, $domain['name'], $chosen['exp'], $cert['refDoc'],
            $this->rand->range(-1, 1.5), $this->rand->range(0.8, 1.5),
            $this->rand->pick(['Understand', 'Apply'])
        );
    }

    /** SAT algebra/geometry bank (w1). */
    private function w1(int $index, array $domain, string $objective, array $cert): array
    {
        $l = $this->rand->int(2, 8);
        $u = $this->rand->int(2, 8);
        $o = $l * $u + $this->rand->int(1, 5);
        $radius = $this->rand->int(5, 11);
        $distance = $this->rand->int(6, 9);
        $base = (int) floor(($o - $u) / $l);
        $candidates = [
            [
                'q' => "If {$l}x + {$u} = {$o} and x is an integer, what is the value of x? (Original SAT Algebra - {$objective})",
                'opts' => [(string) $base, (string) ($base + 1), (string) ($base - 1), (string) ($base + 2)],
                'correct' => 0,
                'exp' => "Solve: {$l}x = {$o} - {$u} = " . ($o - $u) . ", x = " . (($o - $u) / $l) . ". Ref: {$cert['refDoc']} - Algebra linear equations.",
            ],
            [
                'q' => "A circle with center O has radius {$radius}. If chord AB is {$distance} units from center, what is length of AB? (Original Geometry - Approx) Scenario " . ($index + 1),
                'opts' => ['Use Pythagorean: half-chord = sqrt(r^2 - d^2)', 'Diameter', 'Radius * 2', 'Cannot determine'],
                'correct' => 0,
                'exp' => "Geometry principle: distance from center to chord forms right triangle. Ref: {$cert['refDoc']} - Geometry & Trigonometry - Circle theorems.",
            ],
            [
                'q' => 'Passage: Researchers studied student performance with spaced repetition vs cramming. Spaced group retained 35% more after 30 days. Which choice best supports claim that spaced repetition improves long-term retention? (SAT Information & Ideas)',
                'opts' => [
                    'Spaced group scored 35% higher on 30-day delayed test than cramming group',
                    'Both groups scored same on immediate test',
                    'Cramming group studied longer total hours',
                    'Researchers preferred spaced repetition',
                ],
                'correct' => 0,
                'exp' => "Evidence must directly support retention claim. 35% higher on delayed test is direct evidence. Ref: {$cert['refDoc']} - Information and Ideas.",
            ],
        ];
        $a = $this->rand->pick($candidates);
        $h = $this->mapOptions($a['opts'], $a['correct']);

        return $this->finish(
            "{$cert['code']} {$domain['name']} - {$objective}",
            $a['q'], $h, $domain['name'], $a['exp'], $cert['refDoc'],
            $this->rand->range(-1.2, 1.8), $this->rand->range(0.9, 1.6),
            $this->rand->pick(['Apply', 'Analyze'])
        );
    }

    /** Generic official-objectives bank (yu) — Azure, CompTIA, Academic, Finance, Health, India. */
    private function yu(int $index, array $domain, string $objective, array $cert): array
    {
        $stems = [
            "Scenario " . ($index + 1) . ': ' . $this->rand->pick(self::COMPANIES) . " is implementing {$domain['name']} for {$objective}. Team encounters " . $this->rand->pick(['performance bottleneck', 'security gap', 'compliance requirement', 'scalability challenge']) . '. Which approach aligns with official objectives?',
            "For objective \"{$objective}\" in {$domain['name']}, which of the following best demonstrates " . $this->rand->pick(self::BLOOMS) . " level understanding per {$cert['refDoc']}?",
            "A practitioner must apply \"{$objective}\" in a real-world context at {$this->rand->pick(self::COMPANIES)}. Which decision is MOST appropriate? (Original Q" . ($index + 1) . ')',
        ];
        $correct = "Apply {$objective} using {$cert['refDoc']} best practices: implement least privilege, validate inputs, monitor, and document per official guidance";
        $distractors = [
            "Ignore {$objective} and use workaround to save time",
            "Use deprecated method for {$domain['name']} to maintain backward compatibility without assessment",
            "Disable logging and monitoring for {$domain['name']} to improve performance",
            "Hardcode credentials and open firewall for {$objective} implementation",
        ];
        $pool = $this->rand->shuffle([$correct, ...array_slice($this->rand->shuffle($distractors), 0, 3)]);
        $shuffled = $this->rand->shuffle($pool);
        $options = [];
        foreach ($shuffled as $o => $text) {
            $options[] = ['key' => chr(65 + $o), 'text' => $text, 'is_correct' => $text === $correct];
        }

        return $this->finish(
            "{$cert['code']} {$domain['name']} Obj {$this->rand->int(1, 4)}.{$this->rand->int(1, 4)} - {$objective}",
            $this->rand->pick($stems), $options, $domain['name'],
            "Correct because it follows {$cert['refDoc']} for {$objective}. Distractors violate security, governance, or operational best practices. Original question verifiable by objective mapping.",
            $cert['refDoc'],
            $this->rand->range(-1.5, 2), $this->rand->range(0.8, 1.8),
            $this->rand->pick(self::BLOOMS)
        );
    }

    /** Wrap a finished bank item with the shared IRT + metadata envelope. */
    private function finish(
        string $objectiveRef,
        string $content,
        array $options,
        string $domain,
        string $explanation,
        string $reference,
        float $difficulty,
        float $discrimination,
        string $bloom
    ): array {
        return [
            'objective_reference' => $objectiveRef,
            'content' => $content,
            'options' => $options,
            'difficulty' => round($difficulty, 2),
            'discrimination' => round($discrimination, 2),
            'guessing' => 0.25,
            'domain' => $domain,
            'bloom_level' => $bloom,
            'explanation' => $explanation,
            'reference' => $reference,
        ];
    }

    /** @param array<int,string> $opts */
    private function mapOptions(array $opts, int $correct): array
    {
        $mapped = [];
        foreach ($opts as $v => $text) {
            $mapped[] = ['key' => chr(65 + $v), 'text' => $text, 'is_correct' => $v === $correct];
        }
        $shuffled = $this->rand->shuffle($mapped);
        foreach ($shuffled as $v => $option) {
            $shuffled[$v]['key'] = chr(65 + $v);
        }
        return $shuffled;
    }
}