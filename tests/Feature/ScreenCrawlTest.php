<?php

namespace Tests\Feature;

use App\Models\{PassimarkCertificationTrack, PassimarkProgress, PassimarkSession, User};
use Database\Seeders\PassimarkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenCrawlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PassimarkSeeder::class);
    }

    /**
     * Crawl every screen a real user can land on and assert each returns a real
     * Inertia page that actually has a component file behind it. This is the
     * guard against the failure mode where a route exists but renders a page
     * nobody ever built, or a page file exists that no route can reach.
     */
    public function test_every_reachable_screen_renders_a_real_component(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();
        $track = PassimarkCertificationTrack::where('is_active', true)->firstOrFail();
        $session = PassimarkSession::where('question_count', '>', 0)->firstOrFail();

        $guestRoutes = [
            '/login',
            '/register',
            '/',
            '/passimark/lock',
            '/passimark/splash',
            '/passimark/intro',
            '/passimark/auth',
        ];

        $learnerRoutes = [
            '/',
            '/profile',
            '/settings',
            '/passimark/focus',
            '/passimark/permissions',
            '/certs/cissp',
            '/certs/' . $track->certKey(),
        ];

        // The omnibox endpoint is a JSON API, not a screen, so it is asserted
        // separately below rather than crawled as a page.
        $apiRoutes = [
            '/catalog/search?q=security' => 'data',
        ];

        $staffRoutes = [
            '/',
            '/admin/passimark',
            '/admin/import',
            '/admin/reports/learners',
            '/admin/reports/attempts',
            '/admin/reports/sessions',
            '/admin/reports/questions',
            '/admin/reports/tracks',
            '/admin/reports/approvals',
        ];

        $checked = [];

        foreach ($guestRoutes as $uri) {
            $response = $this->get($uri);
            $this->assertTrue(
                $response->isOk() || $response->isRedirect(),
                "guest {$uri} returned {$response->getStatusCode()}"
            );

            if ($response->isOk()) {
                $component = $this->componentFrom($response, $uri);
                $this->assertTrue(
                    $this->componentFileExists($component),
                    "guest {$uri} rendered '{$component}' which has no file"
                );
            }

            $checked[] = $uri;
        }

        foreach ($learnerRoutes as $uri) {
            $response = $this->actingAs($student)->get($uri);
            $this->assertTrue($response->isOk(), "learner {$uri} returned {$response->getStatusCode()}");

            $component = $this->componentFrom($response, $uri);
            $this->assertTrue(
                $this->componentFileExists($component),
                "learner {$uri} rendered '{$component}' which has no file"
            );
            $checked[] = $uri;
        }

        foreach ($staffRoutes as $uri) {
            $response = $this->actingAs($admin)->get($uri);
            $this->assertTrue($response->isOk(), "staff {$uri} returned {$response->getStatusCode()}");

            $component = $this->componentFrom($response, $uri);
            $this->assertTrue(
                $this->componentFileExists($component),
                "staff {$uri} rendered '{$component}' which has no file"
            );
            $checked[] = $uri;
        }

        // '/' is intentionally reachable by more than one role, so assert the crawl
        // actually covered a meaningful number of distinct screens.
        $this->assertGreaterThanOrEqual(20, count(array_unique($checked)));

        foreach ($apiRoutes as $uri => $key) {
            $response = $this->actingAs($student)->get($uri);
            $response->assertOk("api {$uri} did not respond");
            $this->assertArrayHasKey($key, json_decode($response->getContent(), true) ?? []);
        }
    }

    /** The omnibox must actually return matches for a real query, not an empty list. */
    public function test_omnibox_search_returns_real_matches(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();

        $response = $this->actingAs($student)->get('/catalog/search?q=cissp');
        $response->assertOk();

        $payload = json_decode($response->getContent(), true);

        $this->assertNotEmpty($payload['data'], 'omnibox returned zero hits for a real cert key');
        $this->assertArrayHasKey('cert_key', $payload['data'][0]);
        $this->assertArrayHasKey('bundle_url', $payload['data'][0]);
    }

    /** A learner must be able to start a session from the dashboard's own CTA. */
    public function test_learner_has_a_working_session_start_path(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $progress = PassimarkProgress::where('user_id', $student->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->first();

        $this->assertNotNull($progress, 'seeded learner has no startable session');

        $this->actingAs($student)
            ->post("/passimark/session/{$progress->session_id}/start", ['mode' => 'cat'])
            ->assertRedirect();
    }

    /** Every Inertia render target in the controllers must have a component file. */
    public function test_no_controller_renders_a_page_that_does_not_exist(): void
    {
        $renders = [];

        foreach (glob(app_path('Http/Controllers/*.php')) as $file) {
            preg_match_all("/Inertia::render\(\s*'([^']+)'/", file_get_contents($file), $m);
            foreach ($m[1] as $component) {
                $renders[$component] = true;
            }
        }

        $this->assertNotEmpty($renders, 'no Inertia::render calls found - parser is wrong');

        foreach (array_keys($renders) as $component) {
            $this->assertTrue(
                $this->componentFileExists($component),
                "controller renders '{$component}' but resources/js/Pages/{$component}.jsx does not exist"
            );
        }
    }

    /**
     * Every page a learner or staff member can land on must set a document title.
     * app.blade.php has no static <title> fallback, so a screen without <Head>
     * renders with the URL as its title and nothing in the suite would notice.
     */
    public function test_every_reachable_screen_sets_a_document_title(): void
    {
        $student = User::where('email', 'student@passimark.com')->firstOrFail();
        $admin = User::where('email', 'admin@passimark.com')->firstOrFail();

        $this->assertStringNotContainsString(
            '<title>',
            file_get_contents(resource_path('views/app.blade.php')),
            'app.blade.php grew a static <title>; the per-screen assertion below needs revisiting'
        );

        $screens = [
            [null, '/login'],
            [null, '/register'],
            [null, '/passimark/lock'],
            [null, '/passimark/splash'],
            [null, '/passimark/intro'],
            [null, '/passimark/auth'],
            [$student, '/passimark/focus'],
            [$student, '/passimark/permissions'],
            [$student, '/'],
            [$student, '/profile'],
            [$student, '/settings'],
            [$admin, '/admin/passimark'],
        ];

        foreach ($screens as [$user, $uri]) {
            $response = $user ? $this->actingAs($user)->get($uri) : $this->get($uri);
            $component = $this->componentFrom($response, $uri);
            $source = file_get_contents(resource_path('js/Pages/' . $component . '.jsx'));

            $this->assertMatchesRegularExpression(
                '/<Head\s+title=/',
                $source,
                "{$component} (at {$uri}) sets no <Head title>, so the tab shows the raw URL"
            );
        }
    }

    /**
     * app.jsx must not eagerly inline every screen. `import.meta.glob(...,
     * { eager: true })` puts all 28 screens in the entry chunk, so a learner
     * opening the login form downloads the exam engine and the admin control
     * center. Assert the source is lazy AND that the built output really split.
     */
    public function test_screens_are_code_split_not_eagerly_inlined(): void
    {
        $source = file_get_contents(resource_path('js/app.jsx'));

        $this->assertStringNotContainsString(
            "eager: true",
            $source,
            'app.jsx eagerly inlines every page into the entry chunk'
        );

        $this->assertMatchesRegularExpression(
            '/import\.meta\.glob\(/',
            $source,
            'app.jsx no longer registers its pages at all'
        );

        $entry = $this->entryChunk();

        if (! $entry) {
            $this->markTestSkipped('no built entry chunk; run npm run build');
        }

        $code = file_get_contents($entry);

        // A marker string unique to each heavy screen: if code splitting works,
        // none of these can be in the entry chunk.
        foreach ([
            'Admin Control Center' => 'Passimark/Admin',
            'Assessment result' => 'Passimark/Result',
            'Find a certification' => 'Omnibox',
        ] as $marker => $owner) {
            $this->assertStringNotContainsString(
                $marker,
                $code,
                "{$owner} is inlined into the entry chunk; code splitting regressed"
            );
        }

        $chunks = glob(public_path('build/assets/*.js'));
        $this->assertGreaterThan(
            1,
            count($chunks),
            'the build emitted a single chunk, so nothing is code split'
        );
    }

    /** The entry chunk is whatever the Vite manifest points the app entry at. */
    private function entryChunk(): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (! file_exists($manifest)) {
            return null;
        }

        $data = json_decode(file_get_contents($manifest), true);
        $file = $data['resources/js/app.jsx']['file'] ?? null;

        return $file ? public_path('build/' . $file) : null;
    }

    private function componentFileExists(string $component): bool
    {
        return file_exists(resource_path('js/Pages/' . $component . '.jsx'));
    }

    /**
     * Pull the component name out of whichever shape Inertia answered with: a bare
     * JSON body for XHR requests, or the data-page attribute embedded in the full
     * blade HTML for a first (document) request. Reading the raw body keeps the
     * failure message useful when a route answers with something else entirely.
     */
    private function componentFrom($response, string $uri): string
    {
        $body = $response->getContent();

        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['component'])) {
            return $decoded['component'];
        }

        if (preg_match('/data-page="([^"]+)"/', $body, $m)) {
            $page = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
            if (isset($page['component'])) {
                return $page['component'];
            }
        }

        $this->fail("{$uri} did not return an Inertia page. First 200 bytes: " .
            substr(preg_replace('/\s+/', ' ', $body), 0, 200));
    }
}
