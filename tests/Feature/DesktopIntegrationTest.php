<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PsmkZip;
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DesktopIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    private function student(): User
    {
        return User::where('email', 'student@passimark.com')->firstOrFail();
    }

    public function test_desktop_runtime_info_endpoint_returns_vce_superiority_specs(): void
    {
        $response = $this->actingAs($this->student())
            ->getJson('/api/desktop/info');

        $response->assertOk();
        $response->assertJson([
            'engine' => 'Tauri 2.0 Rust Core',
            'desktop_version' => '2.0.0',
            'client_parity' => 'VCE Superiority Standard',
            'capabilities' => [
                'multi_window_exhibits' => true,
                'detached_scratchpad' => true,
                'vce_single_key_shortcuts' => true,
                'offline_sqlite' => true,
            ],
        ]);

        $associations = $response->json('supported_file_associations');
        $this->assertArrayHasKey('psmk', $associations);
        $this->assertArrayHasKey('vce', $associations);
    }

    public function test_desktop_package_inspector_validates_psmk_archive(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'psmk_test_') . '.psmk';
        $archiveBinary = PsmkZip::build([
            'manifest.json' => json_encode([
                'format' => 'passimark',
                'format_version' => '1.0',
                'package_id' => 'pmk-pkg-desktop-test',
                'title' => 'Desktop Test Exam',
            ]),
            'content.json' => json_encode([
                'session' => ['title' => 'Desktop Session'],
                'questions' => [],
            ]),
        ]);
        file_put_contents($tempPath, $archiveBinary);

        $uploaded = new UploadedFile($tempPath, 'desktop-exam.psmk', 'application/zip', null, true);

        $response = $this->actingAs($this->student())
            ->postJson('/api/desktop/inspect', [
                'file' => $uploaded,
            ]);

        $response->assertOk();
        $response->assertJson([
            'is_valid' => true,
            'format' => 'passimark_package',
            'extension' => 'psmk',
            'summary' => [
                'manifest' => [
                    'package_id' => 'pmk-pkg-desktop-test',
                    'title' => 'Desktop Test Exam',
                ],
            ],
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
}
