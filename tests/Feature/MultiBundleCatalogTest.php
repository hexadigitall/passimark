<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkExam;
use App\Models\PassimarkQuestion;
use App\Models\PassimarkSession;
use App\Services\AdminAnalytics;
use App\Services\PassimarkPackage\PackageExporter;
use App\Services\PassimarkPackage\PackageImporter;
use Tests\TestCase;

/**
 * Sprint 7.6: a certification is a category (cert_key); each track row is one bundle/
 * variant. placeBundle() is the single path seeds, imports and admins use so content
 * always lands in the right category row — reusing its own row, adopting a leftover
 * placeholder, or forking a de-conflicted slug for a genuinely new bundle.
 */
class MultiBundleCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    private function admin(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@passimark.com', 'password' => bcrypt('password'), 'role' => 'admin']);
    }

    public function test_two_bundles_share_a_cert_category_with_deconflicted_slugs(): void
    {
        $first = PassimarkCertificationTrack::placeBundle([
            'slug' => 'cissp', 'title' => 'CISSP', 'cert_key' => 'cissp', 'variant_label' => 'Legacy v1',
        ], 'seed:passimark-v1');

        $second = PassimarkCertificationTrack::placeBundle([
            'slug' => 'cissp', 'title' => 'ISC2 CISSP - Gold Standard', 'cert_key' => 'cissp', 'variant_label' => 'Worldwide',
        ], 'seed:worldwide-17');

        $this->assertFalse($first['created']);
        $this->assertTrue($second['created']);
        $this->assertNotSame($first['track']->id, $second['track']->id);
        $this->assertSame('cissp', $first['track']->slug);
        $this->assertSame('cissp-v2', $second['track']->slug);
        $this->assertSame('seed:passimark-v1', $first['track']->source);

        $rows = PassimarkCertificationTrack::orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['cissp', 'cissp'], $rows->pluck('cert_key')->all());
        $this->assertSame('cissp', $rows[0]->certKey());
        $this->assertSame('cissp', $rows[1]->certKey());
    }

    public function test_reseeding_the_same_source_reuses_its_row(): void
    {
        $a = PassimarkCertificationTrack::placeBundle(['slug' => 'cissp', 'title' => 'CISSP', 'cert_key' => 'cissp'], 'seed:cissp-bundle');
        $b = PassimarkCertificationTrack::placeBundle(['slug' => 'cissp', 'title' => 'CISSP', 'cert_key' => 'cissp'], 'seed:cissp-bundle');

        $this->assertFalse($a['created']);
        $this->assertFalse($b['created']);
        $this->assertSame($a['track']->id, $b['track']->id);
        $this->assertSame('cissp', $b['track']->slug);
        $this->assertSame('seed:cissp-bundle', $b['track']->source);
        $this->assertSame(1, PassimarkCertificationTrack::count());
    }

    public function test_a_leftover_placeholder_is_adopted_by_the_first_real_bundle(): void
    {
        // migrate:fresh leaves the default legacy cissp placeholder (migration 000003).
        $placeholder = PassimarkCertificationTrack::where('slug', 'cissp')->where('source', 'legacy')->firstOrFail();

        $placed = PassimarkCertificationTrack::placeBundle([
            'slug' => 'cissp', 'title' => 'ISC2 CISSP - Gold Standard', 'cert_key' => 'cissp', 'region' => 'USA-IT-SECURITY',
        ], 'seed:worldwide-17');

        $this->assertFalse($placed['created']);
        $this->assertSame($placeholder->id, $placed['track']->id);
        $this->assertSame('cissp', $placed['track']->slug);
        $this->assertSame('seed:worldwide-17', $placed['track']->source);
        $this->assertSame('cissp', $placed['track']->cert_key);
        $this->assertSame('USA-IT-SECURITY', $placed['track']->region);
        $this->assertSame(1, PassimarkCertificationTrack::count());
    }

    public function test_a_third_bundle_in_one_category_gets_the_next_free_slug(): void
    {
        foreach (['seed:passimark-v1', 'seed:worldwide-17', 'seed:community-v1'] as $i => $source) {
            PassimarkCertificationTrack::placeBundle([
                'slug' => 'cissp', 'title' => "CISSP #{$i}", 'cert_key' => 'cissp',
            ], $source);
        }

        $slugs = PassimarkCertificationTrack::orderBy('id')->pluck('slug')->all();
        $this->assertSame(['cissp', 'cissp-v2', 'cissp-v3'], $slugs);
        $this->assertSame(3, PassimarkCertificationTrack::where('cert_key', 'cissp')->count());
    }

    public function test_admin_can_create_and_update_bundles_with_cert_category_fields(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->post('/admin/certification-tracks', [
            'slug' => 'cism', 'title' => 'CISM', 'is_active' => true,
            'cert_key' => 'cism', 'variant_label' => 'ISACA Standard',
        ])->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertNotNull($id);
        $this->assertSame('cism', $response->json('data.cert_key'));
        $this->assertSame('ISACA Standard', $response->json('data.variant_label'));

        $this->actingAs($admin)->put("/admin/certification-tracks/{$id}", [
            'cert_key' => 'cism', 'variant_label' => 'ISACA 2026',
        ])->assertOk();

        $this->assertSame('ISACA 2026', PassimarkCertificationTrack::find($id)->variant_label);
    }

    public function test_imports_stamp_source_and_fork_into_a_variant_slug(): void
    {
        $source = $this->makeSession();
        $package = PackageExporter::exportModule($source);
        $file = tempnam(sys_get_temp_dir(), 'psmkmb') . '.psmk';
        file_put_contents($file, $package['binary']);

        $first = PackageImporter::import($file);
        $track = PassimarkCertificationTrack::orderByDesc('id')->first();
        $this->assertSame($package['package_id'], str_replace('import:', '', $track->source));
        $this->assertSame('cissp', $track->cert_key);

        $second = PackageImporter::import($file);
        $this->assertSame(0, $second['created']['tracks']);
        $this->assertSame(1, PassimarkCertificationTrack::count());

        // A different package aimed at the same category must not clobber the first bundle.
        $other = PackageExporter::exportModule($this->makeSession());
        $otherFile = tempnam(sys_get_temp_dir(), 'psmkmb') . '.psmk';
        file_put_contents($otherFile, $other['binary']);
        $otherImport = PackageImporter::import($otherFile);

        $this->assertSame(1, $otherImport['created']['tracks']);
        $this->assertSame(['cissp', 'cissp-v2'], PassimarkCertificationTrack::orderBy('id')->pluck('slug')->all());
        $this->assertSame('cissp', PassimarkCertificationTrack::orderByDesc('id')->first()->cert_key);
    }

    public function test_analytics_report_groups_tracks_into_cert_categories(): void
    {
        PassimarkCertificationTrack::placeBundle(['slug' => 'cissp', 'title' => 'CISSP', 'cert_key' => 'cissp', 'variant_label' => 'Legacy v1'], 'seed:passimark-v1');
        PassimarkCertificationTrack::placeBundle(['slug' => 'cissp', 'title' => 'ISC2 CISSP', 'cert_key' => 'cissp', 'variant_label' => 'Worldwide'], 'seed:worldwide-17');
        PassimarkCertificationTrack::placeBundle(['slug' => 'sec', 'title' => 'CompTIA Security+', 'cert_key' => 'sec'], 'seed:worldwide-17');

        $tiles = app(AdminAnalytics::class)->tiles();
        $certsTile = collect($tiles)->firstWhere('key', 'certs');
        $this->assertNotNull($certsTile);
        $this->assertSame(2, $certsTile['value']);

        $report = app(AdminAnalytics::class)->tracks();
        $this->assertSame(3, $report['summary']['tracks']);
        $this->assertSame(2, $report['summary']['certs']);

        $cisspCategory = collect($report['categories'])->firstWhere('cert_key', 'cissp');
        $this->assertNotNull($cisspCategory);
        $this->assertSame(2, $cisspCategory['variants']);
        $this->assertSame(['cissp', 'cissp-v2'], collect($cisspCategory['tracks'])->pluck('slug')->all());

        $row = collect($report['rows'])->firstWhere('slug', 'cissp-v2');
        $this->assertSame('cissp', $row['cert_key']);
        $this->assertSame('Worldwide', $row['variant_label']);
    }

    public function test_learner_dashboard_carries_cert_category_fields_per_bundle(): void
    {
        PassimarkCertificationTrack::placeBundle(['slug' => 'cissp', 'title' => 'CISSP', 'cert_key' => 'cissp', 'variant_label' => 'Legacy v1'], 'seed:passimark-v1');
        PassimarkCertificationTrack::placeBundle(['slug' => 'cissp', 'title' => 'ISC2 CISSP', 'cert_key' => 'cissp', 'variant_label' => 'Worldwide'], 'seed:worldwide-17');

        $student = User::create(['name' => 'Learner', 'email' => 'learner@passimark.com', 'password' => bcrypt('password'), 'role' => 'student']);

        $page = $this->actingAs($student)->get('/')->assertOk()->viewData('page');
        $tracks = collect($page['props']['tracks']);

        $this->assertSame(['cissp', 'cissp-v2'], $tracks->pluck('slug')->all());
        $this->assertSame(['cissp', 'cissp'], $tracks->pluck('cert_key')->all());
        $this->assertContains('Legacy v1', $tracks->pluck('variant_label')->all());
        $this->assertContains('Worldwide', $tracks->pluck('variant_label')->all());
    }

    // ---------------------------------------------------------------- fixture

    private function makeSession(string $slug = 'cissp'): PassimarkSession
    {
        $track = PassimarkCertificationTrack::firstOrCreate(
            ['slug' => $slug],
            ['title' => 'CISSP', 'description' => 'Prep track.', 'is_active' => true]
        );
        $session = PassimarkSession::create([
            'certification_track_id' => $track->id,
            'cert_slug' => $slug,
            'number' => 1, 'phase' => 1, 'phase_type' => 'lesson', 'order' => 1,
            'title' => 'Security Governance & Frameworks',
            'description' => 'Core governance.', 'domain' => 'Security and Risk Management',
            'is_open' => true, 'pass_score' => 70, 'time_minutes' => 20, 'questions_target' => 1,
        ]);
        $exam = PassimarkExam::create([
            'session_id' => $session->id, 'title' => 'CAT', 'mode' => 'cat',
            'question_count' => 1, 'irt_enabled' => true,
        ]);
        PassimarkQuestion::create([
            'session_id' => $session->id, 'exam_id' => $exam->id,
            'content' => 'What is X?',
            'options' => [
                ['key' => 'A', 'text' => 'Option A', 'is_correct' => true],
                ['key' => 'B', 'text' => 'Option B', 'is_correct' => false],
            ],
            'difficulty' => -0.5, 'discrimination' => 1.2, 'guessing' => 0.25,
            'domain' => 'Security and Risk Management', 'bloom_level' => 'Apply',
            'explanation' => 'Because.', 'reference' => 'ISO 27001', 'correct_key' => 'A',
        ]);
        return $session;
    }
}