<?php

namespace Tests\Feature;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Models\PassimarkTag;
use App\Models\User;
use App\Services\AuthoredBundle;
use Database\Seeders\AuthoredBundleSeeder;
use Database\Seeders\CISSPBundleSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthoredBundleSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
    }

    private function bundle(): AuthoredBundle
    {
        return new AuthoredBundle('cisa');
    }

    private function track(): PassimarkCertificationTrack
    {
        return PassimarkCertificationTrack::where('source', 'seed:authored:cisa')->firstOrFail();
    }

    /**
     * The whole point of the generic loader: a real CISA bank must displace the synthetic
     * catalog content for that cert, in place, not leave a cisa-v2 next to it.
     */
    public function test_it_replaces_synthetic_catalog_content_in_place(): void
    {
        $synthetic = PassimarkCertificationTrack::create([
            'slug' => 'cisa',
            'title' => 'ISACA CISA (synthetic)',
            'cert_key' => 'cisa',
            'source' => 'seed:uniform-205',
            'region' => 'USA-SECURITY',
        ]);
        $oldSession = PassimarkSession::create([
            'certification_track_id' => $synthetic->id,
            'cert_slug' => 'cisa',
            'number' => 1,
            'phase' => 1,
            'phase_type' => 'lesson',
            'title' => 'Foundations',
            'is_open' => true,
            'order' => 1,
            'question_count' => 1,
        ]);
        $oldQuestion = PassimarkQuestion::create([
            'session_id' => $oldSession->id,
            'content' => 'synthetic leftover',
            'options' => [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b'], ['key' => 'C', 'text' => 'c'], ['key' => 'D', 'text' => 'd']],
            'correct_key' => 'A',
            'domain' => 'Foundations',
        ]);

        $this->seed(AuthoredBundleSeeder::class);

        $this->assertSame(1, PassimarkCertificationTrack::where('cert_key', 'cisa')->count(), 'a -v2 track was created next to the synthetic one');
        $track = $this->track();
        $this->assertSame('cisa', $track->slug, 'the adopted track lost its familiar slug');
        $this->assertSame('seed:authored:cisa', $track->source, 'the track was not repointed to the authored source');
        $this->assertFalse(PassimarkQuestion::where('id', $oldQuestion->id)->exists(), 'synthetic items survived the replacement');
        $this->assertSame(0, PassimarkSession::where('certification_track_id', $track->id)
            ->where('theta_required', '!=', null)->count());
    }

    public function test_replacing_a_track_cleans_the_learners_stale_progress(): void
    {
        $synthetic = PassimarkCertificationTrack::create([
            'slug' => 'cisa', 'title' => 'ISACA CISA', 'cert_key' => 'cisa', 'source' => 'seed:uniform-205',
        ]);
        $oldSession = PassimarkSession::create([
            'certification_track_id' => $synthetic->id, 'cert_slug' => 'cisa', 'number' => 1,
            'phase' => 1, 'phase_type' => 'lesson', 'title' => 'Foundations', 'is_open' => true,
            'order' => 1, 'question_count' => 1,
        ]);
        $student = User::firstOrCreate(['email' => 'student@passimark.com'], ['name' => 'TechPoet Dimeji', 'password' => 'password']);

        PassimarkProgress::create(['user_id' => $student->id, 'session_id' => $oldSession->id, 'status' => 'open']);

        $this->seed(AuthoredBundleSeeder::class);

        $this->assertSame(0, PassimarkProgress::where('user_id', $student->id)->where('session_id', $oldSession->id)->count());

        $first = PassimarkSession::where('certification_track_id', $this->track()->id)->where('is_open', true)->first();
        $this->assertTrue(
            PassimarkProgress::where('user_id', $student->id)->where('session_id', $first->id)->exists(),
            'the ladder entry point was not reopened for the demo learner'
        );
    }

    public function test_it_builds_one_session_per_domain_sized_to_the_actual_items(): void
    {
        $this->seed(AuthoredBundleSeeder::class);

        $track = $this->track();
        $sessions = PassimarkSession::where('certification_track_id', $track->id)->get();
        $this->assertSame(3, $sessions->count(), 'one session per domain was not created');

        foreach ($sessions as $session) {
            $this->assertSame(70, (int) $session->pass_score);
            $this->assertNull($session->theta_required, "session {$session->id} declares a theta bar");
            $this->assertSame(
                PassimarkQuestion::where('session_id', $session->id)->count(),
                (int) $session->question_count,
                "session {$session->id} advertises more questions than it holds"
            );
        }

        $this->assertSame(7, PassimarkQuestion::whereIn(
            'session_id',
            PassimarkSession::where('certification_track_id', $track->id)->select('id')
        )->count(), 'the cisa file has 7 authored items; the DB should have exactly that');
    }

    public function test_answer_keys_are_spread_and_match_the_flagged_option(): void
    {
        $this->seed(AuthoredBundleSeeder::class);

        $dist = [];
        foreach (PassimarkQuestion::all() as $q) {
            $dist[$q->correct_key] = ($dist[$q->correct_key] ?? 0) + 1;
            $correct = collect($q->options)->firstWhere('is_correct', true);
            $this->assertNotNull($correct, "item {$q->id} has no option flagged correct");
            $this->assertSame($q->correct_key, $correct['key'], "correct_key disagrees with the flagged option");
        }

        $this->assertCount(4, $dist, 'the rotation did not reach all four letters');
        $this->assertLessThanOrEqual(0.40, max($dist) / array_sum($dist), 'one answer letter dominates ('.json_encode($dist).')');
    }

    public function test_no_item_claims_a_psychometric_parameter(): void
    {
        $this->seed(AuthoredBundleSeeder::class);

        foreach (PassimarkQuestion::all() as $q) {
            $this->assertNull($q->difficulty, "item {$q->id} asserts difficulty");
            $this->assertNull($q->discrimination, "item {$q->id} asserts discrimination");
            $this->assertNull($q->guessing, "item {$q->id} asserts guessing");
        }
    }

    public function test_every_item_keeps_its_source_label_and_a_real_citation(): void
    {
        $this->seed(AuthoredBundleSeeder::class);

        $track = $this->track();
        $questions = PassimarkQuestion::whereIn(
            'session_id',
            PassimarkSession::where('certification_track_id', $track->id)->select('id')
        )->get();

        foreach ($questions as $q) {
            $this->assertStringContainsString('CISA practice bank', $q->reference);
            $this->assertMatchesRegularExpression(
                '/ITAF|COBIT|ISO 22301|ISACA/i',
                $q->reference,
                "item {$q->id} does not cite a real source: {$q->reference}"
            );
            $this->assertNotEmpty($q->explanation);
        }

        $cisaTags = PassimarkTag::where('type', 'domain')->whereHas('questions', function ($q) use ($track) {
            $q->whereIn('session_id', PassimarkSession::where('certification_track_id', $track->id)->select('id'));
        })->count();
        $this->assertSame(3, $cisaTags);
    }

    public function test_reseeding_is_idempotent(): void
    {
        $this->seed(AuthoredBundleSeeder::class);
        $trackId = $this->track()->id;
        $sessions = PassimarkSession::count();
        $questions = PassimarkQuestion::count();

        $this->seed(AuthoredBundleSeeder::class);

        $this->assertSame(1, PassimarkCertificationTrack::where('source', 'seed:authored:cisa')->count());
        $this->assertSame($trackId, $this->track()->id, 'reseeding created a second track');
        $this->assertSame($sessions, PassimarkSession::count());
        $this->assertSame($questions, PassimarkQuestion::count());
    }

    public function test_it_does_not_touch_the_cissp_bundle(): void
    {
        $this->seed(CISSPBundleSeeder::class);
        $this->seed(AuthoredBundleSeeder::class);

        $cissp = PassimarkCertificationTrack::where('slug', 'cissp')->firstOrFail();
        $this->assertSame(46, PassimarkSession::where('certification_track_id', $cissp->id)->count());
        $this->assertSame(1025, PassimarkQuestion::whereIn(
            'session_id',
            PassimarkSession::where('certification_track_id', $cissp->id)->select('id')
        )->count());
    }

    /**
     * The pilot real-exam-length banks must themselves hold together: valid, warning-free,
     * sized to the real exam, and split across the official domains at the intended counts.
     */
    #[DataProvider('realLengthBundles')]
    public function test_real_length_bundle_data_meets_exam_spec(string $certKey, int $total, array $domains): void
    {
        $bundle = new AuthoredBundle($certKey);

        [$errors, $warnings] = $bundle->validate();
        $this->assertSame([], $errors, "{$certKey} failed validation");
        $this->assertSame([], $warnings, "{$certKey} raised warnings (e.g. an option-0-dominant file)");

        $this->assertCount($total, $bundle->items(), "{$certKey} is not the real exam length");

        $counts = [];
        foreach ($bundle->items() as $item) {
            $counts[$item['domain']] = ($counts[$item['domain']] ?? 0) + 1;
        }
        foreach ($domains as $domain => $expected) {
            $this->assertSame($expected, $counts[$domain] ?? 0, "{$certKey} domain split for {$domain}");
        }
        $this->assertSameSize($domains, $bundle->domainGroups(), "{$certKey} does not map one session per domain");
    }

    public static function realLengthBundles(): array
    {
        return [
            'ccna' => ['ccna', 102, [
                'Network Fundamentals' => 22,
                'Network Access' => 19,
                'IP Connectivity' => 26,
                'IP Services' => 11,
                'Security Fundamentals' => 15,
                'Automation and Programmability' => 9,
            ]],
            'sec' => ['sec', 90, [
                'General Security Concepts' => 11,
                'Threats, Vulnerabilities, and Mitigations' => 20,
                'Security Architecture' => 16,
                'Security Operations' => 25,
                'Security Program Management and Oversight' => 18,
            ]],
            'aws-saa' => ['aws-saa', 65, [
                'Design Secure Architectures' => 19,
                'Design Resilient Architectures' => 18,
                'Design High-Performing Architectures' => 16,
                'Design Cost-Optimized Architectures' => 12,
            ]],
            'az-104' => ['az-104', 50, [
                'Manage Azure identities and governance' => 13,
                'Implement and manage storage' => 15,
                'Deploy and manage Azure compute resources' => 12,
                'Configure and manage virtual networking' => 10,
            ]],
            'pmp' => ['pmp', 180, [
                'People' => 76,
                'Process' => 90,
                'Business Environment' => 14,
            ]],
            'aws-sap' => ['aws-sap', 65, [
                'Design Solutions for Organizational Complexity' => 17,
                'Design for New Solutions' => 16,
                'Continuous Improvement for Existing Solutions' => 12,
                'Accelerate Workload Migration and Modernization' => 10,
                'Security' => 10,
            ]],
            'jamb' => ['jamb', 140, [
                'English: Comprehension' => 23,
                'English: Lexis and Structure' => 22,
                'English: Oral Forms' => 15,
                'Mathematics: Algebra & Calculus' => 23,
                'Mathematics: Geometry & Trig' => 17,
                'General Science' => 40,
            ]],
            'waec' => ['waec', 50, [
                'Core Concepts' => 18,
                'Problem Solving' => 18,
                'Communication' => 14,
            ]],
            'sat' => ['sat', 98, [
                'Reading & Writing: Information and Ideas' => 14,
                'Reading & Writing: Craft and Structure' => 15,
                'Reading & Writing: Expression of Ideas' => 11,
                'Reading & Writing: Standard English Conventions' => 14,
                'Math: Algebra' => 15,
                'Math: Advanced Math' => 15,
                'Math: Problem Solving & Data' => 7,
                'Math: Geometry & Trigonometry' => 7,
            ]],
            'ielts-a' => ['ielts-a', 80, [
                'Core Concepts' => 28,
                'Problem Solving' => 28,
                'Communication' => 24,
            ]],
            'gre' => ['gre', 54, [
                'Core Concepts' => 19,
                'Problem Solving' => 19,
                'Communication' => 16,
            ]],
            'nclex-rn' => ['nclex-rn', 85, [
                'Management of Care' => 16,
                'Safety and Infection Control' => 10,
                'Health Promotion and Maintenance' => 8,
                'Psychosocial Integrity' => 8,
                'Basic Care and Comfort' => 8,
                'Pharmacological Therapies' => 13,
                'Reduction of Risk Potential' => 10,
                'Physiological Adaptation' => 12,
            ]],
            'acca-f1-f4' => ['acca-f1-f4', 100, [
                'Business & Technology BT/F1' => 50,
                'Management Accounting MA/F2' => 50,
            ]],
            'cfa-l1' => ['cfa-l1', 180, [
                'Quant & Economics' => 63,
                'Investment Valuation' => 63,
                'Portfolio & Ethics' => 54,
            ]],
            'ican-skills' => ['ican-skills', 100, [
                'Financial Reporting & Audit' => 55,
                'Taxation & Business' => 45,
            ]],
            'jee-main' => ['jee-main', 90, [
                'Physics' => 30,
                'Chemistry' => 30,
                'Mathematics' => 30,
            ]],
            'az-900' => ['az-900', 50, [
                'Cloud Concepts' => 13,
                'Azure Architecture and Services' => 13,
                'Azure Management and Governance' => 13,
                'Azure Core Services' => 11,
            ]],
            'ai-900' => ['ai-900', 50, [
                'AI Workloads and Considerations' => 7,
                'Machine Learning on Azure' => 5,
                'Computer Vision on Azure' => 6,
                'Natural Language Processing on Azure' => 6,
                'Generative AI on Azure' => 6,
                'Responsible AI' => 20,
            ]],
            'aws-cp' => ['aws-cp', 65, [
                'Cloud Concepts' => 16,
                'Security and Compliance' => 20,
                'Cloud Technology and Services' => 22,
                'Billing, Pricing, and Support' => 7,
            ]],
            'az-204' => ['az-204', 50, [
                'Develop Azure compute solutions' => 13,
                'Develop for Azure storage' => 9,
                'Implement Azure security' => 11,
                'Monitor, troubleshoot, and optimize Azure solutions' => 8,
                'Connect to and consume Azure services and third-party services' => 9,
            ]],
            'az-305' => ['az-305', 50, [
                'Design identity, governance, and monitoring solutions' => 14,
                'Design data storage solutions' => 13,
                'Design business continuity solutions' => 9,
                'Design infrastructure solutions' => 14,
            ]],
            'az-500' => ['az-500', 50, [
                'Manage identity and access' => 14,
                'Secure networking' => 11,
                'Secure compute, storage, and databases' => 12,
                'Manage security operations' => 13,
            ]],
            'aws-dva' => ['aws-dva', 65, [
                'Development with AWS Services' => 21,
                'Security' => 17,
                'Deployment' => 16,
                'Troubleshooting and Optimization' => 11,
            ]],
            'net' => ['net', 90, [
                'Networking Fundamentals' => 21,
                'Network Implementations' => 18,
                'Network Operations' => 17,
                'Network Security' => 13,
                'Network Troubleshooting' => 21,
            ]],
            'cysa' => ['cysa', 85, [
                'Security Operations' => 28,
                'Vulnerability Management' => 26,
                'Incident Response and Management' => 17,
                'Reporting and Communication' => 14,
            ]],
            'sscp' => ['sscp', 125, [
                'Security Operations and Administration' => 20,
                'Access Controls' => 18,
                'Risk Identification, Monitoring, and Analysis' => 19,
                'Incident Response and Recovery' => 16,
                'Cryptography' => 14,
                'Network and Communications Security' => 20,
                'Systems and Application Security' => 18,
            ]],
            'cism' => ['cism', 150, [
                'Information Security Governance' => 26,
                'Information Security Risk Management' => 30,
                'Information Security Program' => 49,
                'Incident Management' => 45,
            ]],
            'crisc' => ['crisc', 150, [
                'Governance' => 39,
                'IT Risk Assessment' => 30,
                'Risk Response and Reporting' => 48,
                'Technology and Security' => 33,
            ]],
            'capm' => ['capm', 150, [
                'Project Management Fundamentals and Core Concepts' => 54,
                'Predictive, Plan-Based Methodologies' => 26,
                'Agile Frameworks/Methodologies' => 30,
                'Business Analysis Frameworks' => 40,
            ]],
            'aws-soa' => ['aws-soa', 65, [
                'Monitoring, Logging, and Remediation' => 13,
                'Reliability and Business Continuity' => 10,
                'Deployment, Provisioning, and Automation' => 12,
                'Security and Compliance' => 10,
                'Networking and Content Delivery' => 12,
                'Cost and Performance Optimization' => 8,
            ]],
            'dp-900' => ['dp-900', 50, [
                'Core Data Concepts' => 14,
                'Relational Data on Azure' => 11,
                'Non-Relational Data on Azure' => 9,
                'Analytics Workloads on Azure' => 16,
            ]],
            'dp-203' => ['dp-203', 50, [
                'Design and implement data storage' => 9,
                'Design and develop data processing' => 22,
                'Design and implement data security' => 8,
                'Monitor and optimize data storage and data processing' => 11,
            ]],
            'ai-102' => ['ai-102', 50, [
                'Plan and manage an Azure AI solution' => 9,
                'Implement decision support solutions' => 8,
                'Implement computer vision solutions' => 9,
                'Implement natural language processing solutions' => 10,
                'Implement knowledge mining and document intelligence solutions' => 7,
                'Implement generative AI solutions' => 7,
            ]],
            'az-400' => ['az-400', 50, [
                'Design and implement processes and communications' => 9,
                'Design and implement a source control strategy' => 8,
                'Design and implement build and release pipelines' => 22,
                'Design and implement security and compliance' => 6,
                'Monitor and optimize pipelines' => 5,
            ]],
            'gcp-ace' => ['gcp-ace', 50, [
                'Setting up a cloud solution environment' => 10,
                'Planning and configuring a cloud solution' => 15,
                'Deploying and implementing a cloud solution' => 13,
                'Ensuring successful operation of a cloud solution' => 7,
                'Configuring access and security' => 5,
            ]],
            'gcp-pca' => ['gcp-pca', 50, [
                'Designing and planning a cloud solution architecture' => 12,
                'Managing and provisioning a solution infrastructure' => 8,
                'Designing for security and compliance' => 9,
                'Analyzing and optimizing technical and business processes' => 9,
                'Managing implementation' => 5,
                'Ensuring solution and operations reliability' => 7,
            ]],
            'aws-scs' => ['aws-scs', 65, [
                'Threat Detection and Incident Response' => 9,
                'Security Logging and Monitoring' => 12,
                'Infrastructure Security' => 13,
                'Identity and Access Management' => 10,
                'Data Protection' => 12,
                'Management and Security Governance' => 9,
            ]],
            'aws-dop' => ['aws-dop', 75, [
                'SDLC Automation' => 17,
                'Configuration Management and Infrastructure as Code' => 13,
                'Resilient Cloud Solutions' => 11,
                'Monitoring and Logging' => 11,
                'Incident and Event Response' => 10,
                'Security and Compliance' => 13,
            ]],
            'cc' => ['cc', 100, [
                'Security Principles' => 26,
                'Business Continuity, Disaster Recovery, and Incident Response Concepts' => 10,
                'Access Controls Concepts' => 22,
                'Network Security' => 24,
                'Security Operations' => 18,
            ]],
            'cdpse' => ['cdpse', 120, [
                'Privacy Governance' => 41,
                'Privacy Architecture' => 43,
                'Data Lifecycle' => 36,
            ]],
            'cgeit' => ['cgeit', 120, [
                'Governance of Enterprise IT' => 48,
                'IT Resources' => 30,
                'Benefits Realization' => 12,
                'Risk Optimization' => 30,
            ]],
            'casp' => ['casp', 90, [
                'Security Architecture' => 26,
                'Security Operations' => 27,
                'Security Engineering and Cryptography' => 23,
                'Governance, Risk, and Compliance' => 14,
            ]],
            'cipp-e' => ['cipp-e', 90, [
                'Introduction to European Data Protection' => 6,
                'European Data Protection Law and Regulation' => 41,
                'Compliance with European Data Protection Law and Regulation' => 25,
                'International Data Transfers' => 18,
            ]],
            'pentest' => ['pentest', 85, [
                'Planning and Scoping' => 12,
                'Information Gathering and Vulnerability Scanning' => 19,
                'Attacks and Exploits' => 25,
                'Reporting and Communication' => 15,
                'Tools and Code Analysis' => 14,
            ]],
            'cloud' => ['cloud', 90, [
                'Cloud Architecture and Design' => 12,
                'Security' => 18,
                'Deployment' => 20,
                'Operations and Support' => 20,
                'Troubleshooting' => 20,
            ]],
            'gcp-pde' => ['gcp-pde', 50, [
                'Designing data processing systems' => 11,
                'Ingesting and processing the data' => 13,
                'Storing the data' => 10,
                'Preparing and using data for analysis' => 7,
                'Maintaining and automating data workloads' => 9,
            ]],
            'linux' => ['linux', 90, [
                'System Management' => 29,
                'Security' => 19,
                'Scripting, Containers, and Automation' => 17,
                'Troubleshooting' => 25,
            ]],
            'ta-002-hashi' => ['ta-002-hashi', 57, [
                'Understand infrastructure as code (IaC) concepts' => 5,
                "Understand Terraform's purpose (vs other IaC)" => 2,
                'Understand Terraform basics' => 8,
                'Use the Terraform CLI (outside of core workflow)' => 13,
                'Interact with Terraform modules' => 5,
                'Navigate Terraform workflow' => 5,
                'Implement and maintain state' => 7,
                'Read, generate, and modify configuration' => 10,
                'Understand Terraform Cloud capabilities' => 2,
            ]],
            'ccnp-encor' => ['ccnp-encor', 90, [
                'Architecture' => 14,
                'Virtualization' => 9,
                'Infrastructure' => 27,
                'Network Assurance' => 9,
                'Security' => 18,
                'Automation' => 13,
            ]],
            'ccnp-enarsi' => ['ccnp-enarsi', 55, [
                'Layer 3 Technologies' => 19,
                'VPN Technologies' => 11,
                'Infrastructure Security' => 11,
                'Infrastructure Services' => 14,
            ]],
            'ccnp-scor' => ['ccnp-scor', 90, [
                'Security Concepts' => 14,
                'Network Security' => 18,
                'Cloud Security' => 13,
                'Content Security' => 13,
                'Endpoint Protection and Detection' => 11,
                'Secure Network Access, Visibility, and Enforcement' => 21,
            ]],
            'ccie-ent' => ['ccie-ent', 90, [
                'Architecture' => 14,
                'Virtualization' => 9,
                'Infrastructure' => 27,
                'Network Assurance' => 9,
                'Security' => 18,
                'Automation' => 13,
            ]],
            'itil4-f' => ['itil4-f', 40, [
                'Key Concepts of Service Management' => 8,
                'The Four Dimensions of Service Management' => 6,
                'The ITIL Service Value System' => 12,
                'The ITIL Guiding Principles' => 6,
                'ITIL Management Practices' => 8,
            ]],
            'itil4-mp' => ['itil4-mp', 60, [
                'Create, Deliver and Support (CDS)' => 15,
                'Drive Stakeholder Value (DSV)' => 15,
                'High-velocity IT (HVIT)' => 15,
                'Direct, Plan and Improve (DPI)' => 15,
            ]],
            'pmi-acp' => ['pmi-acp', 120, [
                'Agile Principles and Mindset' => 20,
                'Value-driven Delivery' => 24,
                'Stakeholder Engagement' => 18,
                'Team Performance' => 20,
                'Adaptive Planning' => 15,
                'Problem Detection and Resolution' => 12,
                'Continuous Improvement' => 11,
            ]],
            'csm' => ['csm', 50, [
                'Scrum Theory and Principles' => 12,
                'The Scrum Team' => 12,
                'Scrum Events' => 13,
                'Scrum Artifacts and Commitments' => 13,
            ]],
            'psm-i' => ['psm-i', 80, [
                'Scrum Framework' => 30,
                'Scrum Theory' => 10,
                'Scrum Roles' => 15,
                'Scrum Events' => 15,
                'Scrum Artifacts' => 10,
            ]],
            'psm-ii' => ['psm-ii', 30, [
                'Scrum Mastery' => 10,
                'Facilitation and Coaching' => 10,
                'Organizational Agility' => 10,
            ]],
            'pspo-i' => ['pspo-i', 80, [
                'Product Ownership Fundamentals' => 20,
                'Value and Product Backlog Management' => 20,
                'Stakeholders and Customers' => 15,
                'Scrum Framework as Applied by the Product Owner' => 25,
            ]],
            'safe-sa' => ['safe-sa', 45, [
                'Lean-Agile Mindset' => 8,
                'SAFe Principles' => 10,
                'SAFe Lean-Agile Framework' => 15,
                'Roles and Responsibilities' => 12,
            ]],
            'prince2-f' => ['prince2-f', 60, [
                'PRINCE2 Principles' => 8,
                'PRINCE2 Themes' => 22,
                'PRINCE2 Processes' => 24,
                'Tailoring and the PRINCE2 Environment' => 6,
            ]],
            'prince2-p' => ['prince2-p', 68, [
                'Applying PRINCE2 Principles' => 10,
                'Applying PRINCE2 Themes' => 24,
                'Applying PRINCE2 Processes' => 30,
                'Tailoring PRINCE2 to a Project Environment' => 4,
            ]],
            'cbap' => ['cbap', 120, [
                'Business Analysis Planning and Monitoring' => 15,
                'Elicitation and Collaboration' => 15,
                'Requirements Life Cycle Management' => 15,
                'Strategy Analysis' => 20,
                'Requirements Analysis and Design Definition' => 29,
                'Solution Evaluation' => 11,
                'Techniques and Underlying Competencies' => 15,
            ]],
            'pmi-pba' => ['pmi-pba', 175, [
                'Needs Assessment' => 30,
                'Stakeholder Engagement' => 25,
                'Elicitation' => 35,
                'Analysis' => 55,
                'Solution Evaluation' => 30,
            ]],
            'sixsigma-yb' => ['sixsigma-yb', 60, [
                'Define' => 15,
                'Measure' => 15,
                'Analyze' => 12,
                'Improve' => 10,
                'Control' => 8,
            ]],
            'sixsigma-gb' => ['sixsigma-gb', 100, [
                'Define' => 20,
                'Measure' => 25,
                'Analyze' => 22,
                'Improve' => 18,
                'Control' => 15,
            ]],
            'sixsigma-bb' => ['sixsigma-bb', 125, [
                'Define' => 22,
                'Measure' => 28,
                'Analyze' => 30,
                'Improve' => 25,
                'Control' => 20,
            ]],
            'acca-bt' => ['acca-bt', 50, [
                'Business Organisation Structure and Governance' => 12,
                'Business Functions and the Wider Environment' => 10,
                'Technology and Data in Business' => 14,
                'The Accountant in Business and Professional Ethics' => 14,
            ]],
            'acca-ma' => ['acca-ma', 50, [
                'The Nature, Source and Purpose of Management Information' => 10,
                'Cost Accounting Techniques' => 18,
                'Budgeting' => 12,
                'Standard Costing and Performance Measurement' => 10,
            ]],
            'acca-fa' => ['acca-fa', 50, [
                'The Context and Purpose of Financial Reporting' => 8,
                'Recording Transactions and Events' => 18,
                'Preparing a Trial Balance' => 10,
                'Preparing Basic Financial Statements' => 14,
            ]],
            'acca-lw' => ['acca-lw', 50, [
                'Essential Elements of the Legal System' => 8,
                'The Law of Obligations' => 16,
                'Employment Law' => 8,
                'The Formation and Constitution of Business Organisations' => 10,
                'Capital and the Financing of Companies and Management' => 8,
            ]],
            'acca-pm' => ['acca-pm', 50, [
                'Specialist Cost and Management Accounting Techniques' => 14,
                'Decision-making Techniques' => 14,
                'Budgeting' => 10,
                'Standard Costing and Variance Analysis' => 12,
            ]],
            'acca-tx' => ['acca-tx', 50, [
                'The UK Tax System and Administration' => 8,
                'Income Tax and National Insurance' => 16,
                'Chargeable Gains' => 10,
                'Corporation Tax' => 12,
                'VAT and Other Taxes' => 4,
            ]],
            'acca-fr' => ['acca-fr', 50, [
                'The Conceptual and Regulatory Framework for Financial Reporting' => 8,
                'Accounting for Transactions in Financial Statements' => 16,
                'Analysing the Financial Performance of an Entity' => 10,
                'Preparation of Financial Statements (Including Consolidated)' => 16,
            ]],
            'acca-aa' => ['acca-aa', 50, [
                'Audit Framework and Regulation' => 8,
                'Planning and Risk Assessment' => 14,
                'Internal Control' => 12,
                'Audit Evidence and Procedures' => 10,
                'Review and Reporting' => 6,
            ]],
        ];
    }

    #[DataProvider('invalidBundles')]
    public function test_validation_rejects_a_fabricated_or_malformed_bundle(string $fixture, string $needle): void
    {
        $path = database_path('seeders/data/bundles/__fixture-invalid.json');
        try {
            file_put_contents($path, $fixture);
            $bundle = new AuthoredBundle('x-invalid', $path);
            [$errors] = $bundle->validate();
            $this->assertNotEmpty($errors, "fixture should have failed validation");
            $this->assertStringContainsString($needle, implode("\n", $errors));
        } finally {
            @unlink($path);
        }
    }

    public static function invalidBundles(): array
    {
        $options = ['a', 'b', 'c', 'd'];

        return [
            'psychometric key' => [
                '{"cert_key":"x","title":"X","issuer":"Y","content_provenance":"p","source_label":"s","items":[{"id":"1","stem":"q","options":'.json_encode($options).',"correct_index":0,"explanation":"A sufficiently long explanation.","reference":"A real source","difficulty":-1.5}]}',
                'difficulty',
            ],
            'six options' => [
                '{"cert_key":"x","title":"X","issuer":"Y","content_provenance":"p","source_label":"s","items":[{"id":"1","stem":"q","options":["a","b","c","d","e","f"],"correct_index":0,"explanation":"A sufficiently long explanation.","reference":"A real source"}]}',
                'exactly 4',
            ],
            'correct_index out of range' => [
                '{"cert_key":"x","title":"X","issuer":"Y","content_provenance":"p","source_label":"s","items":[{"id":"1","stem":"q","options":'.json_encode($options).',"correct_index":7,"explanation":"A sufficiently long explanation.","reference":"A real source"}]}',
                '0-3',
            ],
            'missing reference' => [
                '{"cert_key":"x","title":"X","issuer":"Y","content_provenance":"p","source_label":"s","items":[{"id":"1","stem":"q","options":'.json_encode($options).',"correct_index":0,"explanation":"A sufficiently long explanation."}]}',
                'reference',
            ],
        ];
    }
}