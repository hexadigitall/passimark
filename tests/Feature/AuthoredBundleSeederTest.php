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
            'acca-fm' => ['acca-fm', 50, [
                'Financial Management Function and Environment' => 10,
                'Working Capital Management' => 12,
                'Investment Appraisal' => 14,
                'Business Finance and Cost of Capital' => 10,
                'Business Valuation and Risk Management' => 4,
            ]],
            'acca-sbl' => ['acca-sbl', 40, [
                'Leadership, Governance and Ethics' => 14,
                'Strategy, Technology and Risk' => 16,
                'Financial Analysis and Organisational Control' => 10,
            ]],
            'acca-sbr' => ['acca-sbr', 50, [
                'Fundamental Ethical and Professional Principles' => 8,
                'The Financial Reporting Framework' => 12,
                'Reporting the Financial Performance of Groups' => 18,
                'Interpretation and Disclosure of Financial Information' => 12,
            ]],
            'cima-ba1' => ['cima-ba1', 60, [
                'Macroeconomic Context of Business' => 20,
                'Microeconomic and Market Forces' => 25,
                'Financial Markets, Institutions and Regulation' => 15,
            ]],
            'cima-p1' => ['cima-p1', 50, [
                'Cost Accounting for Decision and Control' => 18,
                'Budgeting and Budgetary Control' => 16,
                'Short-term Commercial Decision-making' => 16,
            ]],
            'cfa-l2' => ['cfa-l2', 88, [
                'Quantitative Methods' => 6,
                'Economics' => 6,
                'Financial Reporting and Analysis' => 12,
                'Corporate Issuers' => 6,
                'Equity Investments' => 11,
                'Fixed Income' => 12,
                'Derivatives' => 8,
                'Alternative Investments' => 6,
                'Portfolio Management' => 11,
                'Ethics and Professional Standards' => 10,
            ]],
            'cfa-l3' => ['cfa-l3', 88, [
                'Ethics and Professional Standards' => 10,
                'Asset Allocation' => 12,
                'Portfolio Construction and Management' => 18,
                'Performance Measurement' => 10,
                'Derivatives and Risk Management' => 12,
                'Private Wealth and Private Markets' => 14,
                'Portfolio Management for Institutional Investors' => 12,
            ]],
            'frm-p1' => ['frm-p1', 100, [
                'Foundations of Risk Management' => 20,
                'Quantitative Analysis' => 20,
                'Financial Markets and Products' => 30,
                'Valuation and Risk Models' => 30,
            ]],
            'frm-p2' => ['frm-p2', 80, [
                'Market Risk Measurement and Management' => 16,
                'Credit Risk Measurement and Management' => 16,
                'Operational Risk and Resilience' => 16,
                'Liquidity and Treasury Risk Measurement' => 12,
                'Risk Management and Investment Management' => 12,
                'Current Issues in Financial Markets' => 8,
            ]],
            'ican-found' => ['ican-found', 60, [
                'Financial Accounting' => 20,
                'Business Mathematics and Statistics' => 15,
                'Economics' => 15,
                'Business and Management' => 10,
            ]],
            'ican-pro' => ['ican-pro', 60, [
                'Corporate Reporting' => 18,
                'Advanced Audit and Assurance' => 14,
                'Taxation' => 14,
                'Strategic Financial Management' => 14,
            ]],
            'icag-gh' => ['icag-gh', 60, [
                'Financial Accounting' => 20,
                'Management Accounting' => 14,
                'Taxation and Business Law' => 14,
                'Audit and Assurance' => 12,
            ]],
            'saica-itc' => ['saica-itc', 60, [
                'Financial Accounting and Reporting' => 20,
                'Audit and Assurance' => 14,
                'Taxation' => 14,
                'Managerial Accounting and Finance' => 12,
            ]],
            'zica-zm' => ['zica-zm', 60, [
                'Financial Accounting' => 20,
                'Management Accounting' => 14,
                'Taxation and Business Law' => 14,
                'Audit and Assurance' => 12,
            ]],
            'cibn' => ['cibn', 60, [
                'Banking Operations and Practice' => 18,
                'Financial Systems and Economics' => 14,
                'Banking Law and Regulation' => 14,
                'Management and Marketing of Financial Services' => 14,
            ]],
            'cisi' => ['cisi', 50, [
                'Investment, Securities and the Financial Markets' => 14,
                'Investment Operations and Asset Servicing' => 14,
                'Regulation and Compliance' => 12,
                'Taxation and Client Money' => 10,
            ]],
            'cipd-l3' => ['cipd-l3', 60, [
                'Business, Culture and Change in Context' => 15,
                'Principles of People Practice' => 15,
                'Core Behaviours for People Professionals' => 15,
                'Evidence-based Practice' => 15,
            ]],
            'cipd-l5' => ['cipd-l5', 60, [
                'Strategic People Management' => 18,
                'Organisational Performance and Culture' => 15,
                'Talent Management and Workforce Planning' => 15,
                'Employment Relationship and Law' => 12,
            ]],
            'aat-l2' => ['aat-l2', 60, [
                'Bookkeeping Transactions and Controls' => 25,
                'Principles of Costing' => 15,
                'The Business Environment' => 20,
            ]],
            'aat-l4' => ['aat-l4', 60, [
                'Financial Statements of Limited Companies' => 15,
                'Management Accounting: Budgeting' => 15,
                'Management Accounting: Decision and Control' => 15,
                'Accounting Systems and Controls' => 15,
            ]],
            'waec-art' => ['waec-art', 60, [
                'English Language' => 15,
                'Mathematics' => 15,
                'Literature-in-English' => 15,
                'Government' => 15,
            ]],
            'waec-sci' => ['waec-sci', 60, [
                'English Language' => 15,
                'Mathematics' => 15,
                'Physics' => 15,
                'Chemistry and Biology' => 15,
            ]],
            'wassce-gh' => ['wassce-gh', 60, [
                'English Language' => 15,
                'Core Mathematics' => 15,
                'Integrated Science' => 15,
                'Social Studies' => 15,
            ]],
            'neco' => ['neco', 60, [
                'English Language' => 15,
                'Mathematics' => 15,
                'Biology' => 15,
                'Chemistry' => 15,
            ]],
            'nabteb' => ['nabteb', 60, [
                'English Language' => 15,
                'Mathematics' => 15,
                'Physics' => 15,
                'Chemistry' => 15,
            ]],
            'bece-ng' => ['bece-ng', 60, [
                'English Studies' => 15,
                'Mathematics' => 15,
                'Basic Science and Technology' => 15,
                'Social Studies and Civic Education' => 15,
            ]],
            'gce-ng' => ['gce-ng', 60, [
                'English Language' => 15,
                'Mathematics' => 15,
                'Government' => 15,
                'Economics' => 15,
            ]],
            'jamb-sci' => ['jamb-sci', 60, [
                'Use of English' => 15,
                'Physics' => 15,
                'Chemistry' => 15,
                'Biology' => 15,
            ]],
            'jamb-art' => ['jamb-art', 60, [
                'Use of English' => 15,
                'Literature-in-English' => 15,
                'Government' => 15,
                'Christian Religious Studies' => 15,
            ]],
            'jamb-com' => ['jamb-com', 60, [
                'Use of English' => 15,
                'Mathematics' => 15,
                'Economics' => 15,
                'Commerce and Accounting' => 15,
            ]],
            'life-au' => ['life-au', 60, [
                'Australian Values' => 20,
                "Australia's History and Government" => 20,
                'Australian Geography and Symbols' => 20,
            ]],
            'life-uk' => ['life-uk', 60, [
                'The Values and Principles of the UK' => 12,
                'What is the UK' => 12,
                'A Long and Illustrious History' => 18,
                'A Modern, Thriving Society' => 18,
            ]],
            'hsk-1' => ['hsk-1', 60, [
                'Listening' => 30,
                'Reading' => 30,
            ]],
            'hsk-4' => ['hsk-4', 60, [
                'Listening' => 20,
                'Reading' => 20,
                'Writing' => 20,
            ]],
            'hsk-6' => ['hsk-6', 60, [
                'Listening' => 20,
                'Reading' => 20,
                'Writing' => 20,
            ]],
            'jlpt-n1' => ['jlpt-n1', 60, [
                'Vocabulary' => 20,
                'Grammar and Reading' => 20,
                'Listening' => 20,
            ]],
            'jlpt-n2' => ['jlpt-n2', 60, [
                'Vocabulary' => 20,
                'Grammar and Reading' => 20,
                'Listening' => 20,
            ]],
            'jlpt-n3' => ['jlpt-n3', 60, [
                'Vocabulary' => 20,
                'Grammar and Reading' => 20,
                'Listening' => 20,
            ]],
            'jlpt-n5' => ['jlpt-n5', 60, [
                'Vocabulary' => 20,
                'Grammar and Reading' => 20,
                'Listening' => 20,
            ]],
            'topik-i' => ['topik-i', 60, [
                'Listening' => 30,
                'Reading' => 30,
            ]],
            'topik-ii' => ['topik-ii', 60, [
                'Listening' => 20,
                'Writing' => 20,
                'Reading' => 20,
            ]],
            'delf-a2' => ['delf-a2', 60, [
                "Comprehension de l'oral" => 20,
                'Comprehension des ecrits' => 20,
                'Grammaire et Vocabulaire' => 20,
            ]],
            'delf-b1' => ['delf-b1', 60, [
                "Comprehension de l'oral" => 20,
                'Comprehension des ecrits' => 20,
                'Grammaire et Lexique' => 20,
            ]],
            'delf-b2' => ['delf-b2', 60, [
                'Comprehension de l\'oral' => 20,
                'Comprehension des ecrits' => 20,
                'Grammaire et Lexique' => 20,
            ]],
            'dalf-c1' => ['dalf-c1', 60, [
                'Comprehension de l\'oral' => 20,
                'Comprehension des ecrits' => 20,
                'Lexique et Discours' => 20,
            ]],
            'dele-b1' => ['dele-b1', 60, [
                'Comprension de lectura' => 20,
                'Comprension auditiva' => 20,
                'Gramatica y Vocabulario' => 20,
            ]],
            'dele-b2' => ['dele-b2', 60, [
                'Comprension de lectura' => 20,
                'Comprension auditiva' => 20,
                'Gramatica y Vocabulario' => 20,
            ]],
            'goethe-a2' => ['goethe-a2', 60, [
                'Lesen' => 20,
                'Horen' => 20,
                'Grammatik und Wortschatz' => 20,
            ]],
            'goethe-b1' => ['goethe-b1', 60, [
                'Lesen' => 20,
                'Horen' => 20,
                'Grammatik und Wortschatz' => 20,
            ]],
            'goethe-b2' => ['goethe-b2', 60, [
                'Lesen' => 20,
                'Horen' => 20,
                'Grammatik und Wortschatz' => 20,
            ]],
            'goethe-c1' => ['goethe-c1', 60, [
                'Lesen' => 20,
                'Horen' => 20,
                'Grammatik und Wortschatz' => 20,
            ]],
            'celi-b2' => ['celi-b2', 60, [
                'Comprensione della lettura' => 20,
                'Comprensione dell\'ascolto' => 20,
                'Grammatica e Lessico' => 20,
            ]],
            'telc-b2' => ['telc-b2', 60, [
                'Leseverstehen' => 20,
                'Horverstehen' => 20,
                'Sprachbausteine' => 20,
            ]],
            'testdaf' => ['testdaf', 60, [
                'Leseverstehen' => 20,
                'Horverstehen' => 20,
                'Grammatik und Wortschatz' => 20,
            ]],
            'cam-fce' => ['cam-fce', 60, [
                'Reading and Use of English' => 20,
                'Listening' => 20,
                'Vocabulary and Grammar' => 20,
            ]],
            'cam-cae' => ['cam-cae', 60, [
                'Reading and Use of English' => 20,
                'Listening' => 20,
                'Vocabulary and Grammar' => 20,
            ]],
            'cam-cpe' => ['cam-cpe', 60, [
                'Reading and Use of English' => 20,
                'Listening' => 20,
                'Vocabulary and Grammar' => 20,
            ]],
            'ielts-g' => ['ielts-g', 60, [
                'Reading' => 20,
                'Listening' => 20,
                'Grammar and Vocabulary' => 20,
            ]],
            'pte-a' => ['pte-a', 60, [
                'Reading' => 20,
                'Listening' => 20,
                'Grammar and Vocabulary' => 20,
            ]],
            'pte-au' => ['pte-au', 60, [
                'Reading' => 20,
                'Listening' => 20,
                'Grammar and Vocabulary' => 20,
            ]],
            'emsat-eng' => ['emsat-eng', 60, [
                'Reading Comprehension' => 20,
                'Language and Grammar' => 20,
                'Vocabulary' => 20,
            ]],
            'emsat-math' => ['emsat-math', 60, [
                'Algebra' => 15,
                'Geometry and Trigonometry' => 15,
                'Data Analysis and Statistics' => 15,
                'Functions and Calculus Readiness' => 15,
            ]],
            'qiyas-gat' => ['qiyas-gat', 60, [
                'Verbal Reasoning' => 30,
                'Quantitative Reasoning' => 30,
            ]],
            'qiyas-saat' => ['qiyas-saat', 60, [
                'Biology' => 15,
                'Chemistry' => 15,
                'Physics' => 15,
                'Mathematics' => 15,
            ]],
            'tawjihi-jo' => ['tawjihi-jo', 60, [
                'Arabic Language' => 15,
                'English Language' => 15,
                'Mathematics' => 15,
                'Science' => 15,
            ]],
            'thanaweya-eg' => ['thanaweya-eg', 60, [
                'Arabic Language' => 15,
                'English Language' => 15,
                'Mathematics' => 15,
                'Science' => 15,
            ]],
            'bac-ma' => ['bac-ma', 60, [
                'Francais' => 15,
                'Mathematics' => 15,
                'Sciences de la Vie et de la Terre' => 15,
                'Philosophie' => 15,
            ]],
            'moh-uae' => ['moh-uae', 60, [
                'General Medicine and Nursing' => 15,
                'Pharmacology and Medication Safety' => 15,
                'Patient Care and Procedures' => 15,
                'Medical Law and Ethics' => 15,
            ]],
            'haad' => ['haad', 60, [
                'General Medicine and Nursing' => 15,
                'Pharmacology and Medication Safety' => 15,
                'Patient Safety and Procedures' => 15,
                'Professional Ethics and Law' => 15,
            ]],
            'jee-adv' => ['jee-adv', 60, [
                'Physics' => 20,
                'Chemistry' => 20,
                'Mathematics' => 20,
            ]],
            'neet-ug' => ['neet-ug', 60, [
                'Biology' => 30,
                'Chemistry' => 15,
                'Physics' => 15,
            ]],
            'gate-cs' => ['gate-cs', 60, [
                'Algorithms and Data Structures' => 18,
                'Operating Systems and Databases' => 16,
                'Computer Networks and Security' => 14,
                'Theory of Computation and Mathematics' => 12,
            ]],
            'gate-me' => ['gate-me', 60, [
                'Engineering Mechanics and Strength of Materials' => 16,
                'Thermodynamics and Heat Transfer' => 16,
                'Fluid Mechanics and Machinery' => 14,
                'Manufacturing and Industrial Engineering' => 14,
            ]],
            'upsc-pre' => ['upsc-pre', 60, [
                'General Studies Paper I' => 40,
                'CSAT Paper II' => 20,
            ]],
            'upsc-mains' => ['upsc-mains', 60, [
                'Essay and Ethics' => 20,
                'Governance and Polity' => 20,
                'Economy and Environment' => 20,
            ]],
            'cat-india' => ['cat-india', 60, [
                'Verbal Ability and Reading Comprehension' => 20,
                'Data Interpretation and Logical Reasoning' => 20,
                'Quantitative Ability' => 20,
            ]],
            'xat' => ['xat', 60, [
                'Verbal and Logical Ability' => 20,
                'Decision Making' => 20,
                'Quantitative Ability and Data Interpretation' => 20,
            ]],
            'act' => ['act', 60, [
                'English' => 15,
                'Mathematics' => 15,
                'Reading' => 15,
                'Science' => 15,
            ]],
            'lsat' => ['lsat', 60, [
                'Logical Reasoning' => 30,
                'Reading Comprehension' => 30,
            ]],
            'gmat-focus' => ['gmat-focus', 60, [
                'Quantitative Reasoning' => 20,
                'Verbal Reasoning' => 20,
                'Data Insights' => 20,
            ]],
            'mcat' => ['mcat', 60, [
                'Biological and Biochemical Foundations of Living Systems' => 15,
                'Chemical and Physical Foundations of Biological Systems' => 15,
                'Psychological, Social, and Biological Foundations of Behavior' => 15,
                'Critical Analysis and Reasoning Skills' => 15,
            ]],
            'nclex-pn' => ['nclex-pn', 60, [
                'Safe and Effective Care Environment' => 15,
                'Health Promotion and Maintenance' => 15,
                'Psychosocial Integrity' => 15,
                'Physiological Integrity' => 15,
            ]],
            'usmle-s1' => ['usmle-s1', 60, [
                'Biochemistry and Molecular Biology' => 15,
                'Physiology and Pathophysiology' => 15,
                'Microbiology and Immunology' => 15,
                'Pharmacology and Behavioral Science' => 15,
            ]],
            'usmle-s2' => ['usmle-s2', 60, [
                'Internal Medicine' => 15,
                'Surgery and Emergency Medicine' => 15,
                'Pediatrics and Obstetrics-Gynecology' => 15,
                'Psychiatry and Preventive Medicine' => 15,
            ]],
            'usmle-s3' => ['usmle-s3', 60, [
                'Patient Management and Clinical Judgment' => 20,
                'Pharmacology and Therapeutics' => 15,
                'Biostatistics and Epidemiology' => 13,
                'Medical Ethics and Patient Safety' => 12,
            ]],
            'pance' => ['pance', 60, [
                'Cardiovascular and Respiratory' => 15,
                'Gastrointestinal and Genitourinary' => 15,
                'Musculoskeletal and Neurologic' => 15,
                'Endocrine and Behavioral Medicine' => 15,
            ]],
            'bar-mbe' => ['bar-mbe', 60, [
                'Civil Procedure' => 10,
                'Constitutional Law' => 10,
                'Contracts' => 10,
                'Criminal Law and Procedure' => 10,
                'Evidence' => 10,
                'Torts and Real Property' => 10,
            ]],
            'mpre' => ['mpre', 60, [
                'Regulation of the Legal Profession' => 15,
                'The Client-Lawyer Relationship' => 15,
                'Conflicts of Interest' => 15,
                'Litigation and Transactions' => 15,
            ]],
            'fe-exam' => ['fe-exam', 60, [
                'Mathematics and Engineering Sciences' => 18,
                'Statics and Dynamics' => 16,
                'Materials and Mechanics of Materials' => 14,
                'Engineering Economics and Ethics' => 12,
            ]],
            'pe-exam' => ['pe-exam', 60, [
                'Analysis and Design of Systems' => 18,
                'Project Management and Codes' => 16,
                'Engineering Economics and Cost' => 14,
                'Professional Practice and Ethics' => 12,
            ]],
            'ucat-uk' => ['ucat-uk', 60, [
                'Verbal Reasoning' => 15,
                'Decision Making' => 15,
                'Quantitative Reasoning' => 15,
                'Situational Judgement' => 15,
            ]],
            'lnat' => ['lnat', 60, [
                'Reading Comprehension' => 30,
                'Logical Analysis' => 30,
            ]],
            'a-level-math' => ['a-level-math', 60, [
                'Pure Mathematics' => 30,
                'Mechanics and Statistics' => 30,
            ]],
            'gcse-math' => ['gcse-math', 60, [
                'Number' => 15,
                'Algebra' => 15,
                'Ratio, Proportion and Rates of Change' => 15,
                'Geometry and Statistics' => 15,
            ]],
            'a-level-sg' => ['a-level-sg', 60, [
                'H2 Mathematics' => 20,
                'H2 Physics' => 20,
                'H2 Chemistry' => 20,
            ]],
            'o-level-sg' => ['o-level-sg', 60, [
                'English' => 15,
                'Mathematics' => 15,
                'Combined Science' => 15,
                'Humanities' => 15,
            ]],
            'psle-sg' => ['psle-sg', 60, [
                'English' => 15,
                'Mathematics' => 15,
                'Science' => 15,
                'Mother Tongue Languages' => 15,
            ]],
            'atar-au' => ['atar-au', 60, [
                'English' => 15,
                'Mathematics' => 15,
                'Science' => 15,
                'Humanities and Social Sciences' => 15,
            ]],
            'uk-driving' => ['uk-driving', 60, [
                'Alertness and Safety' => 15,
                'Vehicle Safety and Handling' => 15,
                'Road Procedures and Signs' => 15,
                'Incidents and Documentation' => 15,
            ]],
            'icpc' => ['icpc', 60, [
                'Data Structures and Algorithms' => 20,
                'Graphs and Dynamic Programming' => 20,
                'Mathematics and Geometry' => 20,
            ]],
            'toefl-ibt' => ['toefl-ibt', 60, [
                'Listening' => 15,
                'Reading' => 15,
                'Speaking' => 15,
                'Writing' => 15,
            ]],
            'toeic' => ['toeic', 60, [
                'Listening' => 30,
                'Reading' => 30,
            ]],
            'ap-bio' => ['ap-bio', 60, [
                'Molecules and Cells' => 15,
                'Heredity and Evolution' => 15,
                'Organisms and Populations' => 15,
                'Ecology and Experimental Design' => 15,
            ]],
            'ap-calc-ab' => ['ap-calc-ab', 60, [
                'Limits and Continuity' => 15,
                'Differentiation' => 15,
                'Integration and Accumulation' => 15,
                'Differential Equations and Applications' => 15,
            ]],
            'ap-chem' => ['ap-chem', 60, [
                'Atomic Structure and Properties' => 15,
                'Chemical Reactions and Stoichiometry' => 15,
                'Kinetics and Thermodynamics' => 15,
                'Equilibrium and Acids-Bases' => 15,
            ]],
            'ap-phys-1' => ['ap-phys-1', 60, [
                'Kinematics and Dynamics' => 15,
                'Energy and Momentum' => 15,
                'Waves and Oscillations' => 15,
                'Electricity and Circuits' => 15,
            ]],
            'ib-bio-hl' => ['ib-bio-hl', 60, [
                'Cell Biology and Genetics' => 15,
                'Molecular Biology and Metabolism' => 15,
                'Physiology and Ecology' => 15,
                'Evolution and Diversity' => 15,
            ]],
            'ib-chem-hl' => ['ib-chem-hl', 60, [
                'Stoichiometry and Atomic Structure' => 15,
                'Bonding and Energetics' => 15,
                'Kinetics and Equilibrium' => 15,
                'Organic Chemistry' => 15,
            ]],
            'ib-math-aa-hl' => ['ib-math-aa-hl', 60, [
                'Algebra and Functions' => 15,
                'Calculus' => 15,
                'Geometry and Trigonometry' => 15,
                'Statistics and Probability' => 15,
            ]],
            'gaokao-eng' => ['gaokao-eng', 60, [
                'Reading Comprehension' => 15,
                'Language Knowledge' => 15,
                'Cloze' => 15,
                'Writing and Discourse' => 15,
            ]],
            'gaokao-math' => ['gaokao-math', 60, [
                'Algebra' => 15,
                'Functions and Sequences' => 15,
                'Geometry and Vectors' => 15,
                'Probability and Statistics' => 15,
            ]],
            'ssc-cgl' => ['ssc-cgl', 60, [
                'Quantitative Aptitude' => 20,
                'General Intelligence and Reasoning' => 20,
                'English Comprehension' => 20,
            ]],
            'ibps-po' => ['ibps-po', 60, [
                'Quantitative Aptitude' => 15,
                'Reasoning Ability' => 15,
                'English Language' => 15,
                'General Awareness' => 15,
            ]],
            'enem-br' => ['enem-br', 60, [
                'Linguagens e Codigos' => 15,
                'Ciencias Humanas' => 15,
                'Ciencias da Natureza' => 15,
                'Matematica' => 15,
            ]],
            'kcse' => ['kcse', 60, [
                'Mathematics' => 15,
                'English' => 15,
                'Sciences' => 15,
                'Humanities' => 15,
            ]],
            'post-unical' => ['post-unical', 60, [
                'Use of English' => 20,
                'General Knowledge' => 15,
                'Mathematics and Logic' => 15,
                'Subject Combination' => 10,
            ]],
            'post-unilag' => ['post-unilag', 60, [
                'Use of English' => 20,
                'Mathematics and Logic' => 15,
                'General Knowledge' => 15,
                'Subject Combination' => 10,
            ]],
            'a' => ['a', 60, [
                'Mobile Devices and Networking' => 15,
                'Hardware and Virtualization' => 15,
                'Operating Systems and Security' => 15,
                'Troubleshooting and Procedures' => 15,
            ]],
            'dha' => ['dha', 60, [
                'General Medicine and Nursing' => 15,
                'Pharmacology and Medication Safety' => 15,
                'Patient Care and Procedures' => 15,
                'Medical Law and Ethics' => 15,
            ]],
            'ceh' => ['ceh', 60, [
                'Reconnaissance and Footprinting' => 15,
                'Scanning and Enumeration' => 15,
                'System Hacking and Malware' => 15,
                'Web, Wireless and Cloud Security' => 15,
            ]],
            'chfi' => ['chfi', 60, [
                'Forensic Investigation Process' => 15,
                'Disk and File System Forensics' => 15,
                'Network and Mobile Forensics' => 15,
                'Incident Response and Reporting' => 15,
            ]],
            'gcfa' => ['gcfa', 60, [
                'Forensic Data Collection' => 15,
                'Memory and Timeline Analysis' => 15,
                'Anti-Forensics and Artifacts' => 15,
                'Reporting and Methodology' => 15,
            ]],
            'gcih' => ['gcih', 60, [
                'Incident Handling Process' => 15,
                'Attack Techniques and Vectors' => 15,
                'Detection and Containment' => 15,
                'Eradication and Recovery' => 15,
            ]],
            'gpen' => ['gpen', 60, [
                'Reconnaissance and OSINT' => 15,
                'Scanning and Exploitation' => 15,
                'Privilege Escalation and Pivoting' => 15,
                'Post-Exploitation and Reporting' => 15,
            ]],
            'gsec' => ['gsec', 60, [
                'Security Fundamentals and Policy' => 15,
                'Access Control and Cryptography' => 15,
                'Network and Host Security' => 15,
                'Defense in Depth and Operations' => 15,
            ]],
            'oscp' => ['oscp', 60, [
                'Information Gathering and Scanning' => 15,
                'Exploitation and Buffer Overflows' => 15,
                'Web Application Attacks' => 15,
                'Privilege Escalation and Tunneling' => 15,
            ]],
            'gotranscript' => ['gotranscript', 114, [
                'Full and Clean Verbatim' => 19,
                'Timestamps, Speaker Labels and Layout' => 19,
                'Sound Events and Non-Speech Markings' => 19,
                'Numbers, Times and Measurements' => 19,
                'Dashes, Quotation Marks and Italics' => 19,
                'The Test Job, Ratings and House Style' => 19,
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