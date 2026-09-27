<?php

namespace App\Http\Controllers;

use App\Models\{PassimarkCertificationTrack, PassimarkProgress, PassimarkSession};
use App\Services\{Curriculum, TrackProgressService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

/**
 * Learner catalog drill-down (Sprint 7.8):
 *   index  GET /                          certification category tiles (region sections)
 *   cert   GET /certs/{certKey}           bundle cards + cert overview
 *   bundle GET /certs/{certKey}/{slug}    one bundle: session ladder, θ, domain mastery
 *
 * Level 0/1 send only aggregates; the heavy per-track reads live in TrackProgressService
 * and are used solely on the bundle screen.
 */
class PassimarkCatalogController extends Controller
{
    public function __construct(private readonly TrackProgressService $progress)
    {
    }

    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        $this->ensureEnrolled();

        $region = $request->string('region')->toString();

        $tracks = PassimarkCertificationTrack::query()
            ->where('is_active', true)
            ->when($region, fn ($q) => $q->where('region', $region))
            ->withCount('sessions')
            ->orderBy('region')
            ->orderBy('title')
            ->get();

        $done = $this->progress->doneCountsByTrack($userId);
        $thetas = $this->progress->thetaByTrack($userId);

        $categories = $tracks
            ->groupBy(fn (PassimarkCertificationTrack $track) => $track->certKey())
            ->map(function ($group, $certKey) use ($done, $thetas) {
                $total = (int) $group->sum('sessions_count');
                $completed = (int) $group->sum(fn ($track) => $done[$track->id] ?? 0);
                $theta = null;
                foreach ($group as $track) {
                    if (isset($thetas[$track->id])) {
                        $theta = $theta === null ? $thetas[$track->id] : max($theta, $thetas[$track->id]);
                    }
                }

                return [
                    'cert_key' => $certKey,
                    'title' => $group->first()->title,
                    'region' => $group->first()->region,
                    'bundle_count' => $group->count(),
                    'sessions_total' => $total,
                    'sessions_done' => $completed,
                    'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                    'theta' => $theta !== null ? round($theta, 2) : null,
                    'has_progress' => $completed > 0,
                    'variants' => $group->pluck('variant_label')->filter()->unique()->values()->all(),
                ];
            })
            ->sortBy([['region', 'asc'], ['title', 'asc']])
            ->values();

        $sections = $categories
            ->groupBy(fn ($category) => $category['region'] ?: 'General')
            ->map(fn ($items, $regionName) => [
                'region' => $regionName,
                'categories' => $items->values()->all(),
            ])
            ->values();

        $trackIds = $tracks->pluck('id')->all();

        return Inertia::render('Passimark/Dashboard', [
            'sections' => $sections,
            'region' => $region,
            'focus' => $this->focusPreference(),
            'stats' => [
                'categories' => $categories->count(),
                'bundles' => $tracks->count(),
                'sessions_total' => (int) $tracks->sum('sessions_count'),
                'sessions_done' => (int) array_sum(array_intersect_key($done, array_flip($trackIds))),
            ],
            // The dashboard is focus-first. `sections` is the explore/browse payload and
            // is only rendered when the learner explicitly opens it, so a single total
            // is enough to label that affordance honestly.
            'catalogTotal' => $categories->count(),
            'continueSession' => $this->continuePayload($userId),
        ]);
    }

    /**
     * The learner's saved focus (rung 5), resolved back to a real catalog row so the
     * dashboard can show the title rather than a bare cert_key.
     *
     * Carries the two things a one-student dashboard actually needs to be specific:
     * the next actionable session in that track, and a bounded cohort of related
     * certifications. Without these the dashboard has nothing to show except the
     * 205-tile catalog wall, which is the problem this replaces.
     */
    private function focusPreference(): ?array
    {
        $key = Auth::user()?->preferences['focus'] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        $track = PassimarkCertificationTrack::query()
            ->where('is_active', true)
            ->get()
            ->first(fn (PassimarkCertificationTrack $candidate) => $candidate->certKey() === mb_strtolower($key));

        if (! $track) {
            return null;
        }

        $userId = (int) Auth::id();
        $done = $this->progress->doneCountsByTrack($userId);
        $thetas = $this->progress->thetaByTrack($userId);
        $total = (int) $track->sessions()->count();
        $completed = (int) ($done[$track->id] ?? 0);

        return [
            'cert_key' => $track->certKey(),
            'title' => $track->title,
            'slug' => $track->slug,
            'region' => $track->region ?: 'General',
            'url' => route('passimark.cert', ['certKey' => $track->certKey()]),
            'sessions_done' => $completed,
            'sessions_total' => $total,
            'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'theta' => isset($thetas[$track->id]) ? round($thetas[$track->id], 2) : null,
            'next_session' => $this->nextSessionFor($track, $userId),
            'cohort' => $this->focusCohort($track, $done, $thetas),
        ];
    }

    /**
     * The one session the learner should do next inside the focused track: the first
     * still-outstanding assessable session in ladder order. Open work wins over
     * untouched work so a half-finished session is never buried.
     *
     * @return array{session_id:int,title:string,number:?int,status:string,phase_type:?string,question_count:int,url:string,locked:bool}|null
     */
    private function nextSessionFor(PassimarkCertificationTrack $track, int $userId): ?array
    {
        $statuses = PassimarkProgress::query()
            ->where('user_id', $userId)
            ->pluck('status', 'session_id');

        $next = $track->sessions()
            ->where('question_count', '>', 0)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->first(function (PassimarkSession $session) use ($statuses) {
                $status = $statuses[$session->id] ?? 'locked';

                return ! in_array($status, ['passed', 'certified', 'completed'], true);
            });

        if (! $next) {
            return null;
        }

        $status = $statuses[$next->id] ?? 'locked';

        return [
            'session_id' => $next->id,
            'title' => $next->title,
            'number' => $next->number,
            'status' => $status,
            'phase_type' => $next->phase_type,
            'question_count' => (int) $next->question_count,
            'locked' => $status === 'locked',
            'url' => route('passimark.bundle', [
                'certKey' => $track->certKey(),
                'track' => $track->slug,
            ]),
        ];
    }

    /**
     * A short, ranked list of certifications related to the focus, so the dashboard
     * can show "more like this" without ever rendering the full catalog.
     *
     * Ranking is deliberate and cheap: other bundles of the same cert first, then
     * siblings sharing the cert_key family prefix (aws, cissp, jlpt...), then the
     * rest of the same region. Only the focused track's own progress counts, so the
     * cohort stays a genuine suggestion rather than a second progress board.
     *
     * @return array<int,array<string,mixed>>
     */
    private function focusCohort(PassimarkCertificationTrack $track, array $done, array $thetas, int $limit = 6): array
    {
        $focusKey = $track->certKey();
        $family = str_contains($focusKey, '-') ? explode('-', $focusKey)[0] : $focusKey;

        $candidates = PassimarkCertificationTrack::query()
            ->where('is_active', true)
            ->where('id', '!=', $track->id)
            ->where(function ($query) use ($focusKey, $family, $track) {
                $query->whereRaw('LOWER(cert_key) = ?', [$focusKey])
                    ->orWhereRaw('LOWER(cert_key) LIKE ?', [$family . '-%'])
                    ->orWhere('region', $track->region);
            })
            ->withCount('sessions')
            ->get();

        $rank = static function (PassimarkCertificationTrack $candidate) use ($focusKey, $family, $track): int {
            $key = $candidate->certKey();

            if ($key === $focusKey) {
                return 0;
            }

            return str_starts_with($key, $family . '-') ? 1 : ($candidate->region === $track->region ? 2 : 3);
        };

        return $candidates
            ->sortBy(fn (PassimarkCertificationTrack $candidate) => [$rank($candidate), $candidate->title])
            ->take($limit)
            ->map(function (PassimarkCertificationTrack $candidate) use ($done, $thetas) {
                $total = (int) $candidate->sessions_count;
                $completed = (int) ($done[$candidate->id] ?? 0);

                return [
                    'cert_key' => $candidate->certKey(),
                    'title' => $candidate->title,
                    'region' => $candidate->region ?: 'General',
                    'sessions_done' => $completed,
                    'sessions_total' => $total,
                    'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                    'theta' => isset($thetas[$candidate->id]) ? round($thetas[$candidate->id], 2) : null,
                    'has_progress' => $completed > 0,
                    'url' => route('passimark.cert', ['certKey' => $candidate->certKey()]),
                ];
            })
            ->values()
            ->all();
    }

    public function search(Request $request)
    {
        $this->ensureEnrolled();

        $query = mb_strtolower(trim($request->string('q')));

        $catalog = Cache::remember('passimark.catalog.search.lean.v1', 300, function () {
            return PassimarkCertificationTrack::query()
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['cert_key', 'title', 'region', 'slug'])
                ->map(fn (PassimarkCertificationTrack $track) => [
                    'cert_key' => $track->cert_key,
                    'title' => $track->title,
                    'region' => $track->region,
                    'bundle_url' => route('passimark.cert', ['certKey' => $track->cert_key]),
                ])
                ->groupBy('cert_key')
                ->map(fn ($rows) => $rows->first())
                ->values();
        });

        $hits = $query !== ''
            ? $catalog->filter(fn ($t) => str_contains(mb_strtolower($t['title']), $query)
                || str_contains(mb_strtolower($t['cert_key']), $query))
            : $catalog;

        return response()->json([
            'data' => $hits->values()->take(50)->all(),
            'query' => $query,
            'total' => $hits->count(),
        ]);
    }

    public function cert(string $certKey)
    {
        $userId = (int) Auth::id();
        $this->ensureEnrolled();

        $tracks = PassimarkCertificationTrack::query()
            ->forCert($certKey)
            ->where('is_active', true)
            ->withCount('sessions')
            ->orderByRaw('coalesce(variant_label, title)')
            ->get();

        abort_if($tracks->isEmpty(), 404);

        $done = $this->progress->doneCountsByTrack($userId);
        $thetas = $this->progress->thetaByTrack($userId);

        $bundles = $tracks->map(function (PassimarkCertificationTrack $track) use ($done, $thetas, $certKey) {
            $total = (int) $track->sessions_count;
            $completed = (int) ($done[$track->id] ?? 0);

            return [
                'id' => $track->id,
                'slug' => $track->slug,
                'title' => $track->title,
                'variant_label' => $track->variant_label,
                'source' => $track->source,
                'advancement' => $track->advancement,
                'description' => $track->description,
                'sessions_total' => $total,
                'sessions_done' => $completed,
                'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'theta' => isset($thetas[$track->id]) ? round($thetas[$track->id], 2) : null,
                'has_progress' => $completed > 0,
                'is_content_backed' => $total > 0,
                'url' => route('passimark.bundle', ['certKey' => $certKey, 'track' => $track->slug]),
            ];
        })->values();

        $category = [
            'cert_key' => $certKey,
            'title' => $tracks->first()->title,
            'region' => $tracks->first()->region,
            'bundle_count' => $tracks->count(),
            'sessions_total' => (int) $bundles->sum('sessions_total'),
            'sessions_done' => (int) $bundles->sum('sessions_done'),
        ];

        return Inertia::render('Passimark/Cert', [
            'category' => $category,
            'bundles' => $bundles,
            'continueSession' => $this->continuePayload($userId),
        ]);
    }

    public function bundle(Request $request, string $certKey, PassimarkCertificationTrack $track)
    {
        abort_unless($track->certKey() === $certKey, 404);

        $userId = (int) Auth::id();
        $this->ensureEnrolled();

        $sessions = $track->sessions()->orderBy('order')->get();
        $progress = PassimarkProgress::where('user_id', $userId)->get()->keyBy('session_id');

        $payload = [
            'id' => $track->id,
            'slug' => $track->slug,
            'title' => $track->title,
            'cert_key' => $track->certKey(),
            'variant_label' => $track->variant_label,
            'region' => $track->region,
            'advancement' => $track->advancement,
            'description' => $track->description,
            'sessions' => $sessions->map(fn (PassimarkSession $session) => [
                'id' => $session->id,
                'number' => $session->number,
                'title' => $session->title,
                'description' => $session->description,
                'phase_type' => $session->phase_type,
                'assessable' => (int) $session->question_count > 0,
                'optional' => (bool) $session->is_optional,
                'questions_target' => $session->questions_target ?? $session->question_count,
                'progress' => isset($progress[$session->id])
                    ? $progress[$session->id]->only('id', 'status', 'score', 'ability_theta', 'attempts', 'certified_at', 'credential_id')
                    : ['status' => 'locked', 'score' => null, 'ability_theta' => null, 'attempts' => 0],
            ])->values(),
            'theta_history' => $this->progress->thetaHistory($track->id, $userId),
            'domains' => $this->progress->domainAccuracy($track->id, $userId),
        ];

        $siblings = PassimarkCertificationTrack::query()
            ->forCert($certKey)
            ->where('is_active', true)
            ->where('id', '!=', $track->id)
            ->get()
            ->map(fn (PassimarkCertificationTrack $sibling) => [
                'slug' => $sibling->slug,
                'title' => $sibling->title,
                'variant_label' => $sibling->variant_label,
                'url' => route('passimark.bundle', ['certKey' => $certKey, 'track' => $sibling->slug]),
            ])
            ->values();

        return Inertia::render('Passimark/Track', [
            'track' => $payload,
            'category' => [
                'cert_key' => $certKey,
                'title' => $track->title,
                'region' => $track->region,
                'url' => route('passimark.cert', ['certKey' => $certKey]),
            ],
            'siblings' => $siblings,
            'continueSession' => $this->continuePayload($userId),
        ]);
    }

    /** Lazily enroll the learner in the first assessable step of every active track. */
    private function ensureEnrolled(): void
    {
        if (PassimarkProgress::where('user_id', Auth::id())->doesntExist()) {
            Curriculum::enrollFirstSteps(Auth::user());
        }
    }

    private function continuePayload(int $userId): ?array
    {
        $continue = $this->progress->continueSession($userId);
        if ($continue) {
            $continue['url'] = route('passimark.bundle', [
                'certKey' => $continue['cert_key'],
                'track' => $continue['track_slug'],
            ]);
        }
        return $continue;
    }
}
