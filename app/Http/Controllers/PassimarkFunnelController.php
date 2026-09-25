<?php

namespace App\Http\Controllers;

use App\Models\PassimarkProgress;
use App\Services\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Sprint 9.5 Funnel — backend route group for the first-run ladder.
 *
 * Every rung is a REAL named route and renders a REAL Inertia page, so a
 * learner can always advance without a dead anchor. The dedicated React
 * Lock/Splash/Intro/Auth/Focus/Permissions screens land as the funnel-chrome
 * frontend slice (tracker: PENDING). Until then each rung renders the real
 * Dashboard page, so the ladder is navigable end-to-end today and every route
 * name it exposes already resolves.
 */
class PassimarkFunnelController extends Controller
{
    /** Verbatim local gate — identical to PassimarkCatalogController::ensureEnrolled(). */
    private function ensureEnrolled(): void
    {
        if (PassimarkProgress::where('user_id', Auth::id())->doesntExist()) {
            Curriculum::enrollFirstSteps(Auth::user());
        }
    }

    /** Rung 1 — Lock (first-run gate). */
    public function lock(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Lock', [
            'funnel' => [
                'step' => 'lock',
                'next' => route('passimark.funnel.splash'),
                'ladder' => ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'],
            ],
        ]);
    }

    /** Rung 2 — Splash. */
    public function splash(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Splash', [
            'funnel' => [
                'step' => 'splash',
                'next' => route('passimark.funnel.intro'),
                'ladder' => ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'],
            ],
        ]);
    }

    /** Rung 3 — Intro (skippable). */
    public function intro(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Intro', [
            'funnel' => [
                'step' => 'intro',
                'next' => route('passimark.funnel.auth'),
                'ladder' => ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'],
            ],
        ]);
    }

    /** Rung 4 — Auth (account confirmation). */
    public function auth(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Auth', [
            'funnel' => [
                'step' => 'auth',
                'next' => route('passimark.funnel.focus'),
                'ladder' => ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'],
            ],
        ]);
    }

    /** Rung 5 — Preference / Focus setup (≥1 focus, re-editable). */
    public function focus(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Focus', [
            'funnel' => [
                'step' => 'focus',
                'next' => route('passimark.funnel.permissions'),
                'ladder' => ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'],
            ],
        ]);
    }

    /** Rung 6 — Permission prime (skippable). */
    public function permissions(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Permissions', [
            'funnel' => [
                'step' => 'permissions',
                'next' => route('passimark.dashboard'),
                'ladder' => ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'],
            ],
        ]);
    }
}
