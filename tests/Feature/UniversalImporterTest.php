<?php

namespace Tests\Feature;

use App\Models\PassimarkImportBatch;
use App\Models\PassimarkImportItem;
use App\Models\PassimarkSession;
use App\Models\User;
use App\Services\PassimarkImport\CsvSourceAdapter;
use App\Services\PassimarkImport\GiftSourceAdapter;
use App\Services\PassimarkImport\ImportStagingService;
use App\Services\PassimarkImport\MoodleXmlSourceAdapter;
use App\Services\PassimarkImport\QtiSourceAdapter;
use App\Services\PassimarkImport\VceSourceAdapter;
use App\Services\PassimarkPackage\PackageImporter;
use App\Services\PassimarkPackage\PackageValidator;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UniversalImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@passimark.com')->firstOrFail();
    }

    public function test_csv_source_adapter_parses_comma_and_tsv_formats(): void
    {
        $csvContent = implode("\n", [
            'prompt,choice_a,choice_b,choice_c,choice_d,correct_answer,explanation,domain',
            '"Which protocol is connection-oriented?","TCP","UDP","ICMP","IP","A","TCP uses a 3-way handshake.","Networking"',
            '"Select protocols using UDP","DNS","SNMP","BGP","FTP","A,B","DNS and SNMP use UDP.","Networking"',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_csv_') . '.csv';
        file_put_contents($tmpFile, $csvContent);

        $adapter = new CsvSourceAdapter();
        $result = $adapter->extract($tmpFile, ['title' => 'Network Protocols Exam']);
        @unlink($tmpFile);

        $this->assertSame('Network Protocols Exam', $result['metadata']['title']);
        $this->assertCount(2, $result['items']);

        // Item 1: Single choice
        $item1 = $result['items'][0];
        $this->assertSame('single_choice', $item1['item_type']);
        $this->assertSame('Which protocol is connection-oriented?', $item1['prompt']);
        $this->assertCount(4, $item1['choices']);
        $this->assertTrue($item1['choices'][0]['is_correct']);
        $this->assertSame(['A'], $item1['correct_answers']);
        $this->assertSame('TCP uses a 3-way handshake.', $item1['explanation']);

        // Item 2: Multiple choice
        $item2 = $result['items'][1];
        $this->assertSame('multiple_choice', $item2['item_type']);
        $this->assertTrue($item2['choices'][0]['is_correct']);
        $this->assertTrue($item2['choices'][1]['is_correct']);
        $this->assertFalse($item2['choices'][2]['is_correct']);
    }

    public function test_gift_source_adapter_parses_multiple_item_types(): void
    {
        $giftContent = <<<GIFT
// Standard Single Choice
::Q1:: What is the capital of France? {
    ~London
    =Paris#Correct answer!
    ~Berlin
    ~Madrid
    ####Paris is the capital of France.
}

// True/False
::Q2:: The earth is round. {T}

// Matching / Drag & Drop
::Q3:: Match the cloud provider to service {
    =AWS -> EC2
    =Azure -> Virtual Machines
    =GCP -> Compute Engine
}

// Short Answer / Fill in Blank
::Q4:: Two plus two equals {=4 =four}
GIFT;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_gift_') . '.gift';
        file_put_contents($tmpFile, $giftContent);

        $adapter = new GiftSourceAdapter();
        $result = $adapter->extract($tmpFile, ['title' => 'General Knowledge']);
        @unlink($tmpFile);

        $this->assertCount(4, $result['items']);

        // Q1: single_choice
        $this->assertSame('single_choice', $result['items'][0]['item_type']);
        $this->assertCount(4, $result['items'][0]['choices']);
        $this->assertSame('Paris is the capital of France.', $result['items'][0]['explanation']);

        // Q2: True/False -> single_choice
        $this->assertSame('single_choice', $result['items'][1]['item_type']);
        $this->assertTrue($result['items'][1]['choices'][0]['is_correct']);

        // Q3: Matching -> drag_drop
        $this->assertSame('drag_drop', $result['items'][2]['item_type']);
        $this->assertNotNull($result['items'][2]['interactive_payload']);
        $this->assertCount(3, $result['items'][2]['interactive_payload']['slots']);

        // Q4: Fill in Blank
        $this->assertSame('fill_blank', $result['items'][3]['item_type']);
        $this->assertContains('4', $result['items'][3]['correct_answers']);
    }

    public function test_moodle_xml_source_adapter_parses_items_and_extracts_base64_images(): void
    {
        $samplePng = base64_encode("\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82");

        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<quiz>
  <question type="category">
    <category><text>Networking</text></category>
  </question>
  <question type="multichoice">
    <name><text>Topology Analysis</text></name>
    <questiontext format="html">
      <text><![CDATA[Refer to the exhibit. Which device performs routing?]]></text>
      <file name="topology.png" encoding="base64">{$samplePng}</file>
    </questiontext>
    <generalfeedback format="html"><text><![CDATA[Router R1 connects subnet A and B.]]></text></generalfeedback>
    <single>true</single>
    <answer fraction="100" format="html">
      <text><![CDATA[Router R1]]></text>
    </answer>
    <answer fraction="0" format="html">
      <text><![CDATA[Switch SW1]]></text>
    </answer>
  </question>
</quiz>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_mxml_') . '.xml';
        file_put_contents($tmpFile, $xmlContent);

        $adapter = new MoodleXmlSourceAdapter();
        $result = $adapter->extract($tmpFile, ['title' => 'Cisco Topology Quiz']);
        @unlink($tmpFile);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertSame('single_choice', $item['item_type']);
        $this->assertSame('Refer to the exhibit. Which device performs routing?', $item['prompt']);
        $this->assertCount(2, $item['choices']);
        $this->assertTrue($item['choices'][0]['is_correct']);

        // Check image asset extraction
        $this->assertArrayHasKey('assets/topology.png', $result['assets']);
        $this->assertNotEmpty($item['exhibits']);
        $this->assertSame('assets/topology.png', $item['exhibits'][0]['path']);
    }

    public function test_qti_source_adapter_parses_choice_and_order_interactions(): void
    {
        $qtiXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<assessmentItem xmlns="http://www.imsglobal.org/xsd/imsqti_v2p1" identifier="QTI_001" title="OSI Model">
  <responseDeclaration identifier="RESPONSE" cardinality="single" baseType="identifier">
    <correctResponse>
      <value>Choice_B</value>
    </correctResponse>
  </responseDeclaration>
  <itemBody>
    <choiceInteraction responseIdentifier="RESPONSE" shuffle="false" maxChoices="1">
      <prompt>Which layer of the OSI model does IP operate at?</prompt>
      <simpleChoice identifier="Choice_A">Data Link</simpleChoice>
      <simpleChoice identifier="Choice_B">Network</simpleChoice>
      <simpleChoice identifier="Choice_C">Transport</simpleChoice>
      <simpleChoice identifier="Choice_D">Session</simpleChoice>
    </choiceInteraction>
  </itemBody>
  <modalFeedback identifier="FB" outcomeIdentifier="FEEDBACK" showHide="show">
    IP is Layer 3 Network layer protocol.
  </modalFeedback>
</assessmentItem>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_qti_') . '.xml';
        file_put_contents($tmpFile, $qtiXml);

        $adapter = new QtiSourceAdapter();
        $result = $adapter->extract($tmpFile, ['title' => 'OSI Assessment']);
        @unlink($tmpFile);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertSame('single_choice', $item['item_type']);
        $this->assertSame('Which layer of the OSI model does IP operate at?', $item['prompt']);
        $this->assertCount(4, $item['choices']);
        // Choice B is second item
        $this->assertTrue($item['choices'][1]['is_correct']);
        $this->assertSame(['B'], $item['correct_answers']);
    }

    public function test_vce_source_adapter_parses_binary_container_and_extracts_diagrams(): void
    {
        // Construct simulated VCE binary stream with embedded PNG diagram
        $rawPng = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

        $vceText = implode("\r\n", [
            "QUESTION NO: 1",
            "Refer to the topology exhibit. Which routing protocol guarantees loop-free path calculation?",
            "A. RIPv1",
            "B. EIGRP (DUAL)",
            "C. Static default route only",
            "D. Telnet",
            "Answer: B",
            "Explanation: EIGRP uses the Diffusing Update Algorithm (DUAL) to guarantee loop-free convergence.",
            "",
            "QUESTION NO: 2",
            "Which port does HTTPS listen on by default?",
            "A. 80",
            "B. 443",
            "C. 8080",
            "D. 22",
            "Answer: B",
            "Explanation: Standard HTTPS uses TCP 443.",
        ]);

        // Wrap with VCE header bytes and embedded diagram binary
        $binaryVce = "VCE\x00\x04\x00\x00\x00" . $rawPng . "\x00\x00\x00\x01" . $vceText;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_vce_') . '.vce';
        file_put_contents($tmpFile, $binaryVce);

        $adapter = new VceSourceAdapter();
        $result = $adapter->extract($tmpFile, ['title' => 'Cisco CCNA 200-301 Official VCE']);
        @unlink($tmpFile);

        $this->assertSame('Cisco CCNA 200-301 Official VCE', $result['metadata']['title']);
        $this->assertCount(2, $result['items']);

        // Check embedded diagram extraction
        $this->assertNotEmpty($result['assets']);
        $this->assertArrayHasKey('assets/diagram_1.png', $result['assets']);

        // Item 1 has exhibit diagram attached
        $item1 = $result['items'][0];
        $this->assertSame('single_choice', $item1['item_type']);
        $this->assertStringContainsString('routing protocol guarantees loop-free', $item1['prompt']);
        $this->assertCount(4, $item1['choices']);
        $this->assertTrue($item1['choices'][1]['is_correct']); // B is correct
        $this->assertSame(['B'], $item1['correct_answers']);
        $this->assertNotNull($item1['exhibits']);
        $this->assertSame('assets/diagram_1.png', $item1['exhibits'][0]['path']);

        // Item 2
        $item2 = $result['items'][1];
        $this->assertSame('single_choice', $item2['item_type']);
        $this->assertStringContainsString('HTTPS listen on', $item2['prompt']);
        $this->assertTrue($item2['choices'][1]['is_correct']); // 443 is correct
    }

    public function test_import_staging_lifecycle_and_psmk_conversion(): void
    {
        $csvContent = implode("\n", [
            'prompt,choice_a,choice_b,choice_c,choice_d,correct_answer,explanation,domain',
            '"What is the primary role of an SIEM?","Centralized log aggregation and correlation","Packet filtering firewall","Physical door badge reader","Antivirus database","A","SIEM aggregates security events.","Security Operations"',
            '"Select symmetric encryption ciphers","AES","ChaCha20","RSA","ECC","A,B","AES and ChaCha20 are symmetric ciphers.","Cryptography"',
        ]);

        $tmpCsv = tempnam(sys_get_temp_dir(), 'staging_test_') . '.csv';
        file_put_contents($tmpCsv, $csvContent);

        $stagingService = app(ImportStagingService::class);
        $batch = $stagingService->createBatch($tmpCsv, 'csv', $this->admin(), [
            'title' => 'Security Fundamentals Staged Exam',
            'pass_score' => 75,
            'time_minutes' => 45,
        ]);
        @unlink($tmpCsv);

        $this->assertInstanceOf(PassimarkImportBatch::class, $batch);
        $this->assertSame(2, $batch->total_items);
        $this->assertSame(2, $batch->valid_items);
        $this->assertSame(0, $batch->error_items);
        $this->assertSame('staged', $batch->status);

        // Convert batch to .psmk package
        $packageResult = $stagingService->exportToPsmk($batch);

        $this->assertNotEmpty($packageResult['package_id']);
        $this->assertStringEndsWith('.psmk', $packageResult['filename']);
        $this->assertNotEmpty($packageResult['binary']);
        $this->assertSame(2, $packageResult['items_count']);

        // Verify that the generated package passes PackageValidator
        $tempPsmk = tempnam(sys_get_temp_dir(), 'export_val_') . '.psmk';
        file_put_contents($tempPsmk, $packageResult['binary']);
        $validation = PackageValidator::validate($tempPsmk);
        $this->assertTrue($validation['valid']);

        // Ingest generated .psmk into system using PackageImporter
        $importSummary = PackageImporter::import($tempPsmk, ['cert_slug' => 'cissp']);
        @unlink($tempPsmk);

        $this->assertSame('exam', $importSummary['content_type']);
        $this->assertSame(2, $importSummary['created']['questions']);
        $this->assertSame(1, $importSummary['created']['exams']);
    }

    public function test_http_import_endpoints_and_review(): void
    {
        $csvContent = "prompt,choice_a,choice_b,correct_answer\n\"Is encryption recommended?\",\"Yes\",\"No\",\"A\"";
        $uploadedFile = UploadedFile::fake()->createWithContent('security.csv', $csvContent);

        // 1. Upload & Stage
        $response = $this->actingAs($this->admin())
            ->post('/admin/imports/upload', [
                'file' => $uploadedFile,
                'source_type' => 'csv',
                'title' => 'Security Survey',
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $batchId = $response->json('batch.id');
        $this->assertNotNull($batchId);

        // 2. View Batch
        $showResponse = $this->actingAs($this->admin())
            ->get("/admin/imports/{$batchId}", ['Accept' => 'application/json']);

        $showResponse->assertOk();
        $this->assertSame(1, $showResponse->json('batch.total_items'));

        // 3. Approve All Valid
        $reviewResponse = $this->actingAs($this->admin())
            ->postJson("/admin/imports/{$batchId}/review", ['action' => 'approve_all']);

        $reviewResponse->assertOk();

        // 4. Export .psmk package download
        $downloadResponse = $this->actingAs($this->admin())
            ->get("/admin/imports/{$batchId}/export?download=1");

        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('Content-Type', 'application/vnd.passimark.package');
    }
}
