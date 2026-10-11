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