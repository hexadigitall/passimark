<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{PassimarkSession, PassimarkExam, PassimarkQuestion, User, PassimarkProgress, PassimarkCertificationTrack, PassimarkTag};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PassimarkSeeder extends Seeder
{
    public function run(){
        $user = User::firstOrCreate(['email'=>'student@passimark.com'],['name'=>'TechPoet Dimeji','password'=>Hash::make('password')]);
        User::firstOrCreate(['email'=>'admin@passimark.com'],['name'=>'Passimark Instructor','password'=>Hash::make('password'),'role'=>'admin']);
        $track = PassimarkCertificationTrack::placeBundle([
            'slug'=>'cissp',
            'title'=>'CISSP',
            'description'=>'Certified Information Systems Security Professional preparation track.',
            'is_active'=>true,
            'region'=>null,
            'advancement'=>'approval',
            'cert_key'=>'cissp',
            'variant_label'=>'Legacy v1',
        ], 'seed:passimark-v1')['track'];

        $phases = [
            1=>['title'=>'Foundational Domain Mastery & Core Principles','sessions'=>15],
            2=>['title'=>'Applied Security Engineering & Operations','sessions'=>15],
            3=>['title'=>'Advanced Synthesis, Managerial Mindset & Domain Mastery','sessions'=>9],
            4=>['title'=>'Final CAT Simulation, Readiness Calibration','sessions'=>6],
        ];
        $order=1;
        $sessionDefs = [
            1=>['Security Governance & Frameworks','Security and Risk Management'],
            2=>['Personnel Security & Ethics','Security and Risk Management'],
            3=>['Risk Assessment & Threat Modeling','Security and Risk Management'],
            4=>['Business Continuity & BIA','Security and Risk Management'],
            5=>['Data Classification & Ownership','Asset Security'],
            6=>['Data Protection & Sanitization','Asset Security'],
            7=>['Security Design & Formal Models','Security Architecture and Engineering'],
            8=>['Cryptography Fundamentals','Security Architecture and Engineering'],
            9=>['PKI, Digital Signatures & Crypto Attacks','Security Architecture and Engineering'],
            10=>['Physical & Environmental Security','Security Architecture and Engineering'],
            11=>['Network Architecture & Protocols','Communication and Network Security'],
            12=>['Perimeter Defense & Segmentation','Communication and Network Security'],
            13=>['Wireless, Remote Access & Network Attacks','Communication and Network Security'],
            14=>['Access Control & Authentication','Identity and Access Management'],
            15=>['Phase 1 Diagnostic Exam','Benchmark'],
            16=>['Identity Lifecycle, Federation & PAM','Identity and Access Management'],
            17=>['Assessment & Penetration Testing','Security Assessment and Testing'],
            18=>['Control Testing & Auditing','Security Assessment and Testing'],
            19=>['Incident Response & Digital Forensics','Security Operations'],
            20=>['SOC, SIEM/SOAR & Threat Intelligence','Security Operations'],
            21=>['Disaster Recovery Execution & Backups','Security Operations'],
            22=>['Perimeter, Endpoint & Change Management','Security Operations'],
            23=>['SDLC & DevSecOps Integration','Software Development Security'],
            24=>['OWASP Top 10 & Secure Coding Controls','Software Development Security'],
            25=>['Database Security, Malware & API Hardening','Software Development Security'],
            26=>['Third-Party Risk, Supply Chain & Cloud Security','Security and Risk Management'],
            27=>['Cloud Architecture & Virtualization Security','Security Architecture and Engineering'],
            28=>['IAM Advanced & Zero Trust','Identity and Access Management'],
            29=>['Advanced Threats & APT','Security Operations'],
            30=>['Phase 2 Diagnostic Exam','Benchmark'],
            31=>['Domain Integration & Management Mindset','All Domains'],
            32=>['Think Like a CISO Workshop','Managerial'],
            33=>['Executive Risk Reporting','Managerial'],
            34=>['Legal, Compliance & Privacy Deep Dive','Security and Risk Management'],
            35=>['Security Models & Crypto Synthesis','Synthesis'],
            36=>['Network & Access Control Synthesis','Synthesis'],
            37=>['Assessment & Operations Synthesis','Synthesis'],
            38=>['Software Security Synthesis','Synthesis'],
            39=>['Phase 3 Capstone Exam','Benchmark'],
            40=>['CAT Readiness Calibration I','CAT Simulation'],
            41=>['CAT Readiness Calibration II','CAT Simulation'],
            42=>['CAT Full Simulation 150Q I','CAT Simulation'],
            43=>['CAT Full Simulation 150Q II','CAT Simulation'],
            44=>['CAT Weak Domain Targeting','CAT Simulation'],
            45=>['Final CAT Simulation - Exam Day','CAT Simulation'],
            46=>['Textbook Summary & Executive Conclusion','Conclusion'],
        ];

        foreach($sessionDefs as $num=>$def){
            $phase = $num<=15?1:($num<=30?2:($num<=39?3:4));
            $sess = PassimarkSession::create([
                'certification_track_id'=>$track->id,
                'number'=>$num,'phase'=>$phase,'order'=>$order++,
                'title'=>"Session {$num} • {$def[0]}",
                'description'=>$def[0]." - Comprehensive preparation from Hexadigitall textbook",
                'domain'=>$def[1],
                'is_open'=>$num===1,
                'pass_score'=>70,
                'time_limit'=>$phase==4?180:90,
                'question_count'=>$phase==4?150:25
            ]);
            $sess->tags()->syncWithoutDetaching([$this->ensureTag('domain', $sess->domain)->id]);
            // Create 3 exam modes
            foreach(['cat','timed','practice'] as $mode){
                PassimarkExam::create(['session_id'=>$sess->id,'title'=>"{$sess->title} - ".strtoupper($mode),'mode'=>$mode,'question_count'=>$mode==='cat'?150:25]);
            }
            // Seed sample questions for first 5 sessions
            if($num<=5){
                $this->seedSampleQuestions($sess);
            }
        }
        // Unlock first for student
        PassimarkProgress::firstOrCreate(['user_id'=>$user->id,'session_id'=>1],['status'=>'open']);
    }

    private function seedSampleQuestions($sess){
        $samples = [
            1=>[
                ['q'=>'What is the primary difference between ISO 27001 and ISO 27002?','opts'=>[['A','ISO 27001 is certifiable standard, 27002 is code of practice',true],['B','Both are same',false],['C','27002 is mandatory, 27001 optional',false],['D','27001 for private, 27002 for public',false]],'diff'=>-0.5,'exp'=>'ISO 27001 specifies ISMS requirements for certification audit, while 27002 provides implementation guidance.'],
                ['q'=>'At what organizational level must enterprise security policy originate to be legally binding?','opts'=>[['A','Senior Executive Management / Board',true],['B','IT Manager',false],['C','Security Analyst',false],['D','HR Department',false]],'diff'=>-1.0,'exp'=>'Policy must originate from senior management to have organizational authority.'],
                ['q'=>'Which element of DAD triad opposes hashing and digital signatures?','opts'=>[['A','Alteration (attacks Integrity)',true],['B','Disclosure',false],['C','Destruction',false],['D','Denial',false]],'diff'=>0.2,'exp'=>'DAD: Disclosure, Alteration, Destruction. Hashing protects Integrity against Alteration.'],
                ['q'=>'Which security framework component defines the acceptable use of company IT assets?','opts'=>[['A','Acceptable Use Policy',true],['B','Business Impact Analysis',false],['C','Service Level Agreement',false],['D','Change Control Board',false]],'diff'=>-0.7,'exp'=>'An Acceptable Use Policy governs how employees may use organizational assets.'],
                ['q'=>'Which risk response strategy involves purchasing cyber insurance?','opts'=>[['A','Risk transfer',true],['B','Risk avoidance',false],['C','Risk acceptance',false],['D','Risk mitigation',false]],'diff'=>0.1,'exp'=>'Transferring risk shifts financial impact to a third party, such as an insurer.'],
                ['q'=>'What is the main purpose of due diligence in vendor risk management?','opts'=>[['A','Verify a vendor meets security and compliance requirements before engagement',true],['B','Negotiate lower pricing',false],['C','Terminate underperforming contracts',false],['D','Approve marketing materials',false]],'diff'=>0.3,'exp'=>'Due diligence validates a vendor is trustworthy and compliant prior to onboarding.'],
            ],
            2=>[
                ['q'=>'What is mandatory order of precedence for ISC2 Code of Ethics Canons?','opts'=>[['A','Protect society, Act honorably, Provide diligent service, Advance profession',true],['B','Advance profession first',false],['C','Diligent service first',false],['D','No order',false]],'diff'=>0.0,'exp'=>'Canons in order: Protect society, Act honorably, Diligent service, Advance profession.'],
                ['q'=>'Which HR control reduces the risk of a single employee committing undetected fraud?','opts'=>[['A','Mandatory vacation combined with job rotation',true],['B','Unlimited overtime',false],['C','Open floor seating',false],['D','Flexible dress code',false]],'diff'=>-0.2,'exp'=>'Mandatory vacation and job rotation expose irregularities a single employee might otherwise hide.'],
                ['q'=>'What is the purpose of separation of duties?','opts'=>[['A','Prevent any one person from controlling an entire critical process',true],['B','Reduce staffing costs',false],['C','Speed up approvals',false],['D','Simplify onboarding',false]],'diff'=>-0.4,'exp'=>'Separation of duties requires multiple people to complete sensitive tasks, reducing fraud and error risk.'],
                ['q'=>'Which background check element is most relevant before hiring for a privileged access role?','opts'=>[['A','Criminal history and reference verification',true],['B','Preferred programming language',false],['C','Social media follower count',false],['D','Commute distance',false]],'diff'=>0.1,'exp'=>'Privileged roles warrant deeper vetting including criminal history and verified references.'],
            ],
            3=>[
                ['q'=>'What BIA metric dictates synchronous replication vs nightly backup?','opts'=>[['A','RPO - Recovery Point Objective',true],['B','RTO',false],['C','MTD',false],['D','WRT',false]],'diff'=>0.5,'exp'=>'RPO defines data loss tolerance, driving replication strategy.'],
                ['q'=>'Which risk assessment approach assigns numeric values such as Annualized Loss Expectancy?','opts'=>[['A','Quantitative risk analysis',true],['B','Qualitative risk analysis',false],['C','Delphi technique alone',false],['D','SWOT analysis',false]],'diff'=>0.2,'exp'=>'Quantitative analysis uses numeric metrics like ALE, SLE, and ARO.'],
                ['q'=>'What does the formula SLE x ARO calculate?','opts'=>[['A','Annualized Loss Expectancy (ALE)',true],['B','Recovery Time Objective',false],['C','Mean Time Between Failures',false],['D','Total Cost of Ownership',false]],'diff'=>0.4,'exp'=>'ALE equals Single Loss Expectancy multiplied by Annualized Rate of Occurrence.'],
                ['q'=>'Which threat modeling method categorizes threats as Spoofing, Tampering, Repudiation, Information disclosure, Denial of service, and Elevation of privilege?','opts'=>[['A','STRIDE',true],['B','DREAD',false],['C','OCTAVE',false],['D','FAIR',false]],'diff'=>0.3,'exp'=>'STRIDE is a Microsoft-developed threat categorization framework.'],
            ],
            4=>[
                ['q'=>'Which BIA output identifies the maximum tolerable downtime for a business process?','opts'=>[['A','Maximum Tolerable Downtime (MTD)',true],['B','Recovery Point Objective',false],['C','Recovery Time Objective',false],['D','Work Recovery Time',false]],'diff'=>-0.3,'exp'=>'MTD defines the total time a process can be unavailable before causing unacceptable harm.'],
                ['q'=>'What is the correct high-level order of BCP lifecycle phases?','opts'=>[['A','Project initiation, BIA, strategy development, plan development, testing and maintenance',true],['B','Testing, then BIA, then initiation',false],['C','Strategy development before initiation',false],['D','BIA, then testing, then initiation',false]],'diff'=>0.0,'exp'=>'BCP follows a structured lifecycle that begins with initiation and business impact analysis.'],
                ['q'=>'Which recovery site type is fully operational and offers the fastest recovery, at the highest cost?','opts'=>[['A','Hot site',true],['B','Cold site',false],['C','Warm site',false],['D','Reciprocal agreement',false]],'diff'=>0.1,'exp'=>'Hot sites are fully equipped duplicate facilities enabling near-immediate failover.'],
                ['q'=>'During a BIA, which stakeholder group provides the most accurate impact data for a business process?','opts'=>[['A','Business process owners',true],['B','IT help desk staff',false],['C','External auditors',false],['D','Marketing team',false]],'diff'=>-0.1,'exp'=>'Process owners understand operational impact and dependencies best.'],
                ['q'=>'Which metric represents the acceptable amount of data loss, measured in time?','opts'=>[['A','Recovery Point Objective (RPO)',true],['B','Recovery Time Objective',false],['C','Maximum Tolerable Downtime',false],['D','Service Delivery Objective',false]],'diff'=>0.2,'exp'=>'RPO defines the maximum acceptable data loss interval before a disruption.'],
                ['q'=>'What is the primary purpose of a tabletop exercise in business continuity planning?','opts'=>[['A','Validate plan logic and team coordination through discussion, without a full-scale disruption',true],['B','Permanently replace the need for a full disaster recovery test',false],['C','Certify third-party vendors',false],['D','Set the annual security budget',false]],'diff'=>-0.2,'exp'=>'Tabletop exercises test plan logic and communication in a low-risk, discussion-based format.'],
            ],
            5=>[
                ['q'=>"Who is typically accountable for defining an organization's data classification levels?",'opts'=>[['A','Data owner',true],['B','Data custodian',false],['C','End user',false],['D','Physical security guard',false]],'diff'=>-0.3,'exp'=>'Data owners hold accountability for classification and protection requirements.'],
                ['q'=>"What is the role of a data custodian?",'opts'=>[['A',"Implements technical and operational controls per the owner's classification",true],['B','Sets legal liability for a data breach',false],['C','Approves the annual security budget',false],['D','Defines overall business strategy',false]],'diff'=>0.0,'exp'=>"Custodians handle the day-to-day technical maintenance of data per the owner's direction."],
                ['q'=>'Which classification label is commonly used in commercial organizations for the most sensitive data tier?','opts'=>[['A','Confidential / Restricted',true],['B','Public',false],['C','Internal use only',false],['D','Unclassified',false]],'diff'=>0.1,'exp'=>'Commercial organizations often use Confidential or Restricted for their most sensitive data tier.'],
                ['q'=>'What is the primary purpose of data classification?','opts'=>[['A','Apply protective controls proportional to sensitivity and business value',true],['B','Increase storage costs',false],['C','Simplify marketing campaigns',false],['D','Remove the need for encryption',false]],'diff'=>-0.2,'exp'=>'Classification enables security controls that are proportional to the value and sensitivity of the data.'],
                ['q'=>'Which process ensures that data no longer needed is rendered unrecoverable?','opts'=>[['A','Sanitization',true],['B','Replication',false],['C','Classification',false],['D','Provisioning',false]],'diff'=>0.2,'exp'=>'Sanitization renders data unrecoverable, completing the secure data lifecycle.'],
                ['q'=>'Who is responsible for granting data access permissions consistent with classification policy?','opts'=>[['A','Data owner',true],['B','End user',false],['C','External vendor',false],['D','Marketing lead',false]],'diff'=>0.0,'exp'=>'Data owners authorize access consistent with classification policy and business need.'],
            ],
        ];
        $qs = $samples[$sess->number] ?? [];
        foreach($this->fillerQuestions($sess, count($qs), 25 - count($qs)) as $filler){
            $qs[] = $filler;
        }
        foreach($qs as $s){
            $question = PassimarkQuestion::create([
                'session_id'=>$sess->id,
                'content'=>$s['q'],
                'options'=>array_map(fn($o)=>['key'=>$o[0],'text'=>$o[1],'is_correct'=>$o[2]], $s['opts']),
                'difficulty'=>$s['diff'],
                'discrimination'=>1.2,
                'guessing'=>0.25,
                'domain'=>$sess->domain,
                'explanation'=>$s['exp'],
                'bloom_level'=>'Apply'
            ]);
            $question->tags()->syncWithoutDetaching([
                $this->ensureTag('domain', $sess->domain)->id,
                $this->ensureTag('bloom', 'Apply')->id,
            ]);
        }
    }

    private function ensureTag(string $type, string $label): PassimarkTag
    {
        return PassimarkTag::firstOrCreate(['type'=>$type,'slug'=>Str::slug($label)],['label'=>$label]);
    }

    // Deterministic filler used only to top up a session to 25 questions; never assigns a random or missing correct answer.
    private function fillerQuestions($sess, int $startIndex, int $count): array
    {
        if ($count <= 0) return [];
        // All stems must expect a control-category answer so they stay consistent with $optionLabels below.
        $stems = [
            "Which control category best addresses a documented gap in {$sess->domain} found during a recent audit?",
            "A weakness related to {$sess->domain} was discovered during a review. Which control category is the most appropriate first response?",
            "Which control category should be prioritized to reduce risk exposure in {$sess->domain}?",
            "An incident related to {$sess->domain} exposed a control gap. Which control category should be strengthened first?",
        ];
        $optionLabels = [
            'Administrative control - update the relevant policy or procedure',
            'Technical control - enforce the requirement through automated tooling',
            'Physical control - restrict physical access to the affected asset',
            'Detective control - increase monitoring and audit logging',
        ];
        $questions = [];
        for ($i = 0; $i < $count; $i++) {
            $correctIndex = $i % 4;
            $opts = [];
            foreach ($optionLabels as $index => $label) {
                $opts[] = [chr(65 + $index), $label, $index === $correctIndex];
            }
            $questions[] = [
                'q' => $stems[$i % count($stems)] . ' (Item ' . ($startIndex + $i + 1) . ')',
                'opts' => $opts,
                'diff' => round((($i % 9) - 4) / 4, 2),
                'exp' => "Review the {$sess->domain} guidance referenced in the session material for this scenario.",
            ];
        }
        return $questions;
    }
}
