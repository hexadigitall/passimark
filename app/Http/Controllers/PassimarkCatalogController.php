<?php

namespace App\Http\Controllers;

use App\Models\{PassimarkCertificationTrack, PassimarkProgress, PassimarkSession};
use App\Services\{Curriculum, TrackProgressService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'stats' => [
                'categories' => $categories->count(),
                'bundles' => $tracks->count(),
                'sessions_total' => (int) $tracks->sum('sessions_count'),
                'sessions_done' => (int) array_sum(array_intersect_key($done, array_flip($trackIds))),
            ],
            'continueSession' => $this->continuePayload($userId),
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
                'phase_type' => $session->phase_type,
                'questions_target' => $session->questions_target ?? $session->question_count,
                'progress' => isset($progress[$session->id])
                    ? $progress[$session->id]->only('status', 'score', 'ability_theta', 'attempts')
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
