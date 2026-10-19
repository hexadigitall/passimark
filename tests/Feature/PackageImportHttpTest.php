<?php

namespace Tests\Feature;

use App\Models\PassimarkPackageImport;
use App\Models\PassimarkSession;
use App\Models\User;
use App\Support\PsmkZip;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PackageImportHttpTest extends TestCase
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

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    private function manifest(array $overrides = []): array
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

    private function moduleContent(): array
    {
        return [
            'cert_slug' => 'cissp',
            'module' => [
                'external_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'number' => 1,
                'phase' => 1,
                'phase_type' => 'lesson',
                'order' => 1,
                'title' => 'Security Governance',
                'pass_score' => 70,
                'questions_target' => 1,
                'exams' => [[
                    'external_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                    'title' => 'CAT',
                    'mode' => 'cat',
                    'question_count' => 1,
                    'irt_enabled' => true,
                ]],
                'questions' => [[
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
                ]],
            ],
        ];
    }

    private function packageUpload(array $manifest = null, array $content = null): UploadedFile
    {
        $binary = PsmkZip::build([
            'manifest.json' => json_encode($manifest ?? $this->manifest()),
            'content.json' => json_encode($content ?? $this->moduleContent()),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'psmkhttp') . '.psmk';
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'security-governance.psmk', 'application/zip', null, true);
    }

    public function test_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/packages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Passimark/PackageImport')
                ->has('imports'));
    }

    public function test_student_is_forbidden_from_package_routes(): void
    {
        $this->actingAs($this->student())->get('/admin/packages')->assertForbidden();
        $this->actingAs($this->student())
            ->post('/admin/packages/preview', ['package' => $this->packageUpload()])
            ->assertForbidden();
        $this->actingAs($this->student())->post('/admin/packages/import', [])->assertForbidden();
    }

    public function test_preview_reports_the_plan_without_writing_content(): void
    {
        $sessionsBefore = PassimarkSession::count();

        $response = $this->actingAs($this->admin())
            ->post('/admin/packages/preview', ['package' => $this->packageUpload()])
            ->assertOk();

        $response->assertJson([
            'valid' => true,
            'content_type' => 'module',
            'title' => 'Security Governance',
            'cert_slug' => 'cissp',
            'duplicate' => false,
        ]);
        $response->assertJsonPath('counts.sessions', 1);
        $response->assertJsonPath('counts.exams', 1);
        $response->assertJsonPath('counts.questions', 1);

        $this->assertSame($sessionsBefore, PassimarkSession::count());
        $this->assertSame(0, PassimarkPackageImport::count());
    }

    public function test_preview_flags_an_already_imported_checksum(): void
    {
        $file = $this->packageUpload();
        $this->actingAs($this->admin())
            ->post('/admin/packages/import', ['package' => $file, 'on_duplicate' => 'create'])
            ->assertOk();

        $this->actingAs($this->admin())
            ->post('/admin/packages/preview', ['package' => $this->packageUpload()])
            ->assertOk()
            ->assertJson(['duplicate' => true])
            ->assertJsonPath('existing_imports', 1);
    }

    public function test_import_writes_content_and_audits_the_uploader(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/packages/import', [
            'package' => $this->packageUpload(),
            'on_duplicate' => 'skip',
        ])->assertOk();

        $response->assertJsonPath('created.sessions', 1);
        $response->assertJsonPath('created.questions', 1);
        $this->assertSame(0, PassimarkPackageImport::where('status', 'failed')->count());

        $audit = PassimarkPackageImport::latest('id')->first();
        $this->assertSame('imported', $audit->status);
        $this->assertSame('module', $audit->content_type);
        $this->assertSame($this->admin()->id, $audit->uploaded_by);
        $this->assertSame('security-governance.psmk', $audit->original_filename);
    }

    public function test_reimport_is_idempotent_in_skip_mode(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/packages/import', ['package' => $this->packageUpload(), 'on_duplicate' => 'skip'])
            ->assertOk();

        $sessionsAfterFirst = PassimarkSession::count();

        $this->actingAs($this->admin())
            ->post('/admin/packages/import', ['package' => $this->packageUpload(), 'on_duplicate' => 'skip'])
            ->assertOk()
            ->assertJson(['skipped' => true]);

        $this->assertSame($sessionsAfterFirst, PassimarkSession::count());
        $this->assertSame(1, PassimarkPackageImport::where('status', 'skipped')->count());
    }

    public function test_fail_mode_rejects_a_duplicate(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/packages/import', ['package' => $this->packageUpload(), 'on_duplicate' => 'create'])
            ->assertOk();

        $response = $this->actingAs($this->admin())
            ->post('/admin/packages/import', ['package' => $this->packageUpload(), 'on_duplicate' => 'fail'])
            ->assertStatus(422);

        $this->assertStringContainsString('already imported', $response->json('message'));
        $this->assertSame(1, PassimarkPackageImport::where('status', 'failed')->count());
    }

    public function test_invalid_package_preview_returns_errors_and_blocks_import(): void
    {
        // A manifest-only archive fails validation (missing content.json).
        $binary = PsmkZip::build(['manifest.json' => json_encode($this->manifest())]);
        $path = tempnam(sys_get_temp_dir(), 'psmkbad') . '.psmk';
        file_put_contents($path, $binary);
        $bad = new UploadedFile($path, 'broken.psmk', 'application/zip', null, true);

        $preview = $this->actingAs($this->admin())
            ->post('/admin/packages/preview', ['package' => $bad])
            ->assertOk()
            ->assertJson(['valid' => false]);

        $this->assertStringContainsString('Missing required file', implode("\n", $preview->json('errors')));

        $this->actingAs($this->admin())
            ->post('/admin/packages/import', ['package' => $bad, 'on_duplicate' => 'skip'])
            ->assertStatus(422);
    }
}
