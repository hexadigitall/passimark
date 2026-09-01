<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{PassimarkSession, PassimarkExam, PassimarkQuestion, User, PassimarkProgress};
use Illuminate\Support\Facades\Hash;

class PassimarkSeeder extends Seeder
{
    public function run(){
        $user = User::firstOrCreate(['email'=>'student@passimark.com'],['name'=>'TechPoet Dimeji','password'=>Hash::make('password')]);
        User::firstOrCreate(['email'=>'admin@passimark.com'],['name'=>'Passimark Instructor','password'=>Hash::make('password')]);

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
                'number'=>$num,'phase'=>$phase,'order'=>$order++,
                'title'=>"Session {$num} • {$def[0]}",
                'description'=>$def[0]." - Comprehensive preparation from Hexadigitall textbook",
                'domain'=>$def[1],
                'is_open'=>$num===1,
                'pass_score'=>70,
                'time_limit'=>$phase==4?180:90,
                'question_count'=>$phase==4?150:25
            ]);
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
            ],
            2=>[
                ['q'=>'What is mandatory order of precedence for ISC2 Code of Ethics Canons?','opts'=>[['A','Protect society, Act honorably, Provide diligent service, Advance profession',true],['B','Advance profession first',false],['C','Diligent service first',false],['D','No order',false]],'diff'=>0.0,'exp'=>'Canons in order: Protect society, Act honorably, Diligent service, Advance profession.'],
            ],
            3=>[
                ['q'=>'What BIA metric dictates synchronous replication vs nightly backup?','opts'=>[['A','RPO - Recovery Point Objective',true],['B','RTO',false],['C','MTD',false],['D','WRT',false]],'diff'=>0.5,'exp'=>'RPO defines data loss tolerance, driving replication strategy.'],
            ]
        ];
        $qs = $samples[$sess->number] ?? $samples[1];
        foreach($qs as $s){
            PassimarkQuestion::create([
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
        }
        // Fill to 25 with generated
        for($i=count($qs);$i<25;$i++){
            PassimarkQuestion::create([
                'session_id'=>$sess->id,
                'content'=>"Sample CISSP Question #".($i+1)." for {$sess->title}: Which control best mitigates this scenario?",
                'options'=>[['key'=>'A','text'=>'Administrative control - Policy update','is_correct'=>rand(0,3)==0],['key'=>'B','text'=>'Technical control - Encryption','is_correct'=>rand(0,3)==1],['key'=>'C','text'=>'Physical control - CCTV','is_correct'=>rand(0,3)==2],['key'=>'D','text'=>'Deterrent control - Warning sign','is_correct'=>false]],
                'difficulty'=>rand(-20,20)/10,
                'domain'=>$sess->domain,
                'explanation'=>'Refer to textbook domain principles and Think Like a CISO mindset.',
            ]);
        }
    }
}
