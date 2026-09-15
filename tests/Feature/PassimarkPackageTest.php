<?php

namespace Tests\Feature;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkPackageImport;
use App\Models\PassimarkProgress;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\PassimarkPackage\PackageExporter;
use App\Services\PassimarkPackage\PackageImporter;
use App\Services\PassimarkPackage\PackageValidator;
use App\Support\PsmkZip;
use Tests\TestCase;

class PassimarkPackageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    // ---------------------------------------------------------------- fixtures

    private function track(string $slug = 'cissp'): PassimarkCertificationTrack
    {
        return PassimarkCertificationTrack::firstOrCreate(
            ['slug' => $slug],
            ['title' => 'CISSP', 'description' => 'Prep track.', 'is_active' => true]
        );
    }

    private function makeQuestionAttrs(string $prompt = 'What is X?', string $correct = 'A'): array
    {
        return [
            'content' => $prompt,
            'options' => [
                ['key' => 'A', 'text' => 'Option A', 'is_correct' => $correct === 'A'],
                ['key' => 'B', 'text' => 'Option B', 'is_correct' => $correct === 'B'],
                ['key' => 'C', 'text' => 'Option C', 'is_correct' => $correct === 'C'],
            ],
            'correct_key' => $correct,
            'difficulty' => -0.5,
            'discrimination' => 1.2,
            'guessing' => 0.25,
            'domain' => 'Security and Risk Management',
            'bloom_level' => 'Apply',
            'explanation' => 'Because.',
            'reference' => 'ISO 27001',
        ];
    }

    private function makeSession(string $slug = 'cissp', int $order = 1, string $phaseType = 'lesson'): PassimarkSession
    {
        $track = $this->track($slug);
        $session = PassimarkSession::create([
            'certification_track_id' => $track->id,
            'cert_slug' => $slug,
            'number' => $order,
            'phase' => 1,
            'phase_type' => $phaseType,
            'order' => $order,
            'title' => 'Security Governance & Frameworks',
            'description' => 'Core governance.',
            'domain' => 'Security and Risk Management',
            'is_open' => true,
            'pass_score' => 70,
            'theta_required' => null,
            'time_minutes' => 20,
            'questions_target' => 3,
        ]);

        $cat = PassimarkExam::create([
            'session_id' => $session->id,
            'title' => 'Security Governance & Frameworks - CAT',
            'mode' => 'cat',
            'question_count' => 3,
            'time_minutes' => 12,
            'is_final' => false,
            'irt_enabled' => true,
        ]);
        PassimarkExam::create([
            'session_id' => $session->id,
            'title' => 'Security Governance & Frameworks - PRACTICE',
            'mode' => 'practice',
            'question_count' => 5,
            'time_minutes' => 0,
            'is_final' => false,
            'irt_enabled' => false,
        ]);

        foreach (range(1, 3) as $i) {
            PassimarkQuestion::create([...$this->makeQuestionAttrs("Q{$i}: prompt?"), 'session_id' => $session->id, 'exam_id' => $cat->id]);
        }

        return $session;
    }

    private function writeTemp(string $binary, string $suffix = 'psmk'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'psmkpt') . '.' . $suffix;
        file_put_contents($path, $binary);
        return $path;
    }

    // ---------------------------------------------------------------- round trips

    public function test_module_package_round_trips_faithfully(): void
    {
        $source = $this->makeSession();
        $package = PackageExporter::exportModule($source);
        $file = $this->writeTemp($package['binary']);

        $result = PackageValidator::validate($file);
        $this->assertTrue($result['valid'], implode("\n", $result['errors']));

        $summary = PackageImporter::import($file);
        $this->assertSame('module', $summary['content_type']);
        $this->assertSame(['tracks' => 0, 'sessions' => 1, 'exams' => 2, 'questions' => 3], $summary['created']);

        $imported = PassimarkSession::query()->where('id', '!=', $source->id)->firstOrFail();
        $this->assertSame('cissp', $imported->cert_slug);
        $this->assertFalse((bool) $imported->is_open);
        $this->assertSame($source->title, $imported->title);
        $this->assertSame('lesson', $imported->phase_type);
        $this->assertSame(70, $imported->pass_score);

        $this->assertCount(2, $imported->exams);
        $modes = $imported->exams()->pluck('mode')->sort()->values()->all();
        $this->assertSame(['cat', 'practice'], $modes);

        $question = $imported->questions()->first();
        $this->assertSame($source->questions()->first()->content, $question->content);
        $this->assertSame('A', $question->correct_key);
        $this->assertSame(-0.5, $question->difficulty);
        $this->assertSame(1.2, $question->discrimination);
        $this->assertSame(0.25, $question->guessing);
        $this->assertSame('Apply', $question->bloom_level);
        $this->assertSame('cat', $question->exam?->mode);
    }

    public function test_psme_and_psmm_extensions_are_presentation_hints(): void
    {
        $package = PackageExporter::exportModule($this->makeSession());

        foreach (['psme', 'psmm'] as $ext) {
            $summary = PackageImporter::import($this->writeTemp($package['binary'], $ext));
            $this->assertSame(1, $summary['created']['sessions']);
        }
    }

    public function test_exam_package_round_trips(): void
    {
        $source = $this->makeSession();
        $exam = $source->exams()->where('mode', 'cat')->first();

        $package = PackageExporter::exportExam($exam);
        $file = $this->writeTemp($package['binary']);

        $result = PackageValidator::validate($file);
        $this->assertTrue($result['valid'], implode("\n", $result['errors']));

        $summary = PackageImporter::import($file);
        $this->assertSame('exam', $summary['content_type']);
        $this->assertSame(['tracks' => 0, 'sessions' => 1, 'exams' => 1, 'questions' => 3], $summary['created']);

        $importedExam = PassimarkExam::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('cat', $importedExam->mode);
        $this->assertCount(3, $importedExam->questions);
        $this->assertSame($exam->session->title, $importedExam->session->title);
    }

    public function test_course_package_round_trips_whole_track(): void
    {
        $this->makeSession('aws-saa', 1, 'lesson');
        $this->makeSession('aws-saa', 2, 'phase');
        $track = $this->track('aws-saa');

        $package = PackageExporter::exportCourse($track);
        $file = $this->writeTemp($package['binary']);

        $result = PackageValidator::validate($file);
        $this->assertTrue($result['valid'], implode("\n", $result['errors']));

        $summary = PackageImporter::import($file);
        $this->assertSame('course', $summary['content_type']);
        $this->assertSame(['tracks' => 0, 'sessions' => 2, 'exams' => 4, 'questions' => 6], $summary['created']);

        $this->assertSame(4, PassimarkSession::where('cert_slug', 'aws-saa')->count());
    }

    // ---------------------------------------------------------------- validation

    private function buildRaw(array $manifest, ?array $content, array $extraFiles = []): string
    {
        $files = ['manifest.json' => json_encode($manifest)];
        if ($content !== null) {
            $files['content.json'] = json_encode($content);
        }
        return PsmkZip::build([...$files, ...$extraFiles]);
    }

    private function validManifest(array $overrides = []): array
    {
        return array_merge([
            'format' => 'passimark',
            'format_version' => '1.0',
            'package_id' => '11111111-1111-4111-8111-111111111111',
            'content_type' => 'module',
            'title' => 'Security Governance',
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
            'producer' => ['name' => 'Passimark', 'version' => '1.0'],
        ], $overrides);
    }

    private function validModuleContent(array $overrides = []): array
    {
        return array_merge([
            'cert_slug' => 'cissp',
            'module' => array_merge([
                'external_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'number' => 1,
                'phase' => 1,
                'phase_type' => 'lesson',
                'order' => 1,
                'title' => 'Security Governance',
                'pass_score' => 70,
                'questions_target' => 1,
                'exams' => [
                    [
                        'external_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                        'title' => 'CAT',
                        'mode' => 'cat',
                        'question_count' => 1,
                        'irt_enabled' => true,
                    ],
                ],
                'questions' => [
                    [
                        'external_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
                        'type' => 'single_choice',
                        'prompt' => 'Question?',
                        'choices' => [
                            ['id' => 'A', 'text' => 'One'],
                            ['id' => 'B', 'text' => 'Two'],
                        ],
                        'correct_choice_ids' => ['A'],
                        'key' => 'A',
                        'irt_3pl' => ['difficulty' => -0.5, 'discrimination' => 1.2, 'guessing' => 0.25],
                    ],
                ],
            ], $overrides['module'] ?? []),
        ], $overrides);
    }

    private function assertInvalid(string $binary, string $needle): void
    {
        $result = PackageValidator::validate($this->writeTemp($binary));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString($needle, implode("\n", $result['errors']));
    }

    public function test_validation_rejects_bad_manifests_and_archives(): void
    {
        $this->assertInvalid(
            $this->buildRaw($this->validManifest(['format' => 'nope']), $this->validModuleContent()),
            "manifest.format must be 'passimark'"
        );
        $this->assertInvalid(
            $this->buildRaw($this->validManifest(['format_version' => '2.0']), $this->validModuleContent()),
            'Unsupported format major version'
        );
        $this->assertInvalid(
            $this->buildRaw($this->validManifest(['content_type' => 'quiz']), $this->validModuleContent()),
            'manifest.content_type'
        );
        $this->assertInvalid(
            $this->buildRaw($this->validManifest(['package_id' => 'not-a-uuid']), $this->validModuleContent()),
            'package_id must be a UUID'
        );
        $this->assertInvalid(PsmkZip::build(['manifest.json' => json_encode($this->validManifest())]), 'Missing required file');
        $this->assertInvalid(
            $this->buildRaw($this->validManifest(), $this->validModuleContent(), ['evil.txt' => 'x']),
            "Unexpected entry 'evil.txt'"
        );
        $this->assertInvalid(
            $this->buildRaw($this->validManifest(), $this->validModuleContent(), ['dir/manifest.json' => '{}']),
            'unsafe path'
        );
    }

    public function test_validation_rejects_bad_content(): void
    {
        $twoCorrect = $this->validModuleContent();
        $twoCorrect['module']['questions'][0]['correct_choice_ids'] = ['A', 'B'];
        $this->assertInvalid($this->buildRaw($this->validManifest(), $twoCorrect), 'must contain exactly one choice id');

        $irout = $this->validModuleContent();
        $irout['module']['questions'][0]['irt_3pl'] = ['difficulty' => 99, 'discrimination' => 1, 'guessing' => 0.2];
        $this->assertInvalid($this->buildRaw($this->validManifest(), $irout), 'difficulty must be within');

        $dupeUuid = $this->validModuleContent();
        $dupeUuid['module']['questions'][0]['external_id'] = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $this->assertInvalid($this->buildRaw($this->validManifest(), $dupeUuid), 'Duplicate external_id');

        $badSlug = $this->validModuleContent();
        $badSlug['cert_slug'] = 'CISSP';
        $this->assertInvalid($this->buildRaw($this->validManifest(), $badSlug), 'cert_slug');

        $noCorrect = $this->validModuleContent();
        $noCorrect['module']['questions'][0]['correct_choice_ids'] = [];
        $this->assertInvalid($this->buildRaw($this->validManifest(), $noCorrect), 'must contain exactly one choice id');

        $emptyChoices = $this->validModuleContent();
        $emptyChoices['module']['questions'][0]['choices'] = [['id' => 'A', 'text' => 'Only']];
        $this->assertInvalid($this->buildRaw($this->validManifest(), $emptyChoices), '2 to 6 choices');
    }

    public function test_import_is_create_only_and_always_audits(): void
    {
        $source = $this->makeSession();
        $package = PackageExporter::exportModule($source);
        $file = $this->writeTemp($package['binary']);

        $first = PackageImporter::import($file);
        $second = PackageImporter::import($file);

        $this->assertSame(3, PassimarkSession::count());
        $this->assertSame(0, PassimarkProgress::count()); // never touches learner progression
        $this->assertSame(true, (bool) PassimarkSession::where('id', $source->id)->first()->is_open); // original untouched
        $this->assertSame(0, PassimarkSession::where('id', '!=', $source->id)->where('is_open', true)->count());

        $this->assertSame($first['checksum'], $second['checksum']);
        $this->assertSame(2, PassimarkPackageImport::count());
        $audit = PassimarkPackageImport::latest('id')->first();
        $this->assertSame('imported', $audit->status);
        $this->assertSame('module', $audit->content_type);
        $this->assertSame($package['package_id'], $audit->package_id);
        $this->assertSame(['tracks' => 0, 'sessions' => 1, 'exams' => 2, 'questions' => 3], $audit->summary);
    }

    public function test_import_creates_missing_track_and_records_failed_imports(): void
    {
        $package = PackageExporter::exportModule($this->makeSession('fresh-cert'));
        $file = $this->writeTemp($package['binary']);

        PassimarkCertificationTrack::where('slug', 'fresh-cert')->delete();
        PassimarkSession::where('cert_slug', 'fresh-cert')->delete();

        $summary = PackageImporter::import($file);
        $this->assertSame(1, $summary['created']['tracks']);
        $this->assertDatabaseHas('passimark_certification_tracks', ['slug' => 'fresh-cert']);

        // A validation-rejected package never reaches the importer (dry-run), so it leaves no audit.
        $bad = $this->writeTemp(PsmkZip::build(['manifest.json' => json_encode($this->validManifest())]));
        try {
            PackageImporter::import($bad);
            $this->fail('Importing an invalid package should throw.');
        } catch (\Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
        }
        $this->assertSame(0, PassimarkPackageImport::where('status', 'failed')->count());

        // A package that passes validation but is refused inside the transaction records a failed audit.
        $itemBank = $this->buildRaw(
            $this->validManifest(['content_type' => 'item-bank', 'title' => 'Item bank']),
            ['cert_slug' => 'cissp', 'items' => $this->validModuleContent()['module']['questions']]
        );
        $itemBankFile = $this->writeTemp($itemBank);
        $this->assertTrue(PackageValidator::validate($itemBankFile)['valid']);
        try {
            PackageImporter::import($itemBankFile);
            $this->fail('item-bank packages must be refused by version 1 import.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('item-bank', $e->getMessage());
        }
        $failed = PassimarkPackageImport::where('status', 'failed')->latest('id')->first();
        $this->assertNotNull($failed);
        $this->assertArrayHasKey('error', $failed->error_report);
    }
}