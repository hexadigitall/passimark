<?php

namespace App\Http\Controllers;

use App\Models\{PassimarkCertificationTrack, PassimarkProgress};
use App\Services\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Sprint 9.5 Funnel — backend route group for the first-run ladder.
 *
 * Rungs 1-4 (lock/splash/intro/auth) are reachable before an account exists. Rungs 5-7
 * (focus/permissions/dashboard) read and write the learner's own record, so they stay
 * behind auth and are reached by AuthController after a successful sign-in.
 */
class PassimarkFunnelController extends Controller
{
    private const LADDER = ['lock', 'splash', 'intro', 'auth', 'focus', 'permissions', 'dashboard'];

    /** Verbatim local gate — identical to PassimarkCatalogController::ensureEnrolled(). */
    private function ensureEnrolled(): void
    {
        if (! Auth::check()) {
            return;
        }

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
                'ladder' => self::LADDER,
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
                'ladder' => self::LADDER,
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
                'ladder' => self::LADDER,
            ],
        ]);
    }

    /** Rung 4 — Auth (account confirmation). Signs the learner in before the gated rungs. */
    public function auth(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Auth', [
            'funnel' => [
                'step' => 'auth',
                'next' => route('login'),
                'ladder' => self::LADDER,
            ],
        ]);
    }

    /** Rung 5 — Preference / Focus setup (≥1 focus, re-editable). */
    public function focus(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Focus', [
            'options' => $this->focusOptions(),
            'selected' => Auth::user()?->preferences['focus'] ?? null,
            'funnel' => [
                'step' => 'focus',
                'next' => route('passimark.funnel.permissions'),
                'submit' => route('passimark.funnel.focus.update'),
                'ladder' => self::LADDER,
            ],
        ]);
    }

    /**
     * The real certification catalog, grouped by region. The picker offers what actually
     * exists in the DB rather than a hardcoded list, so a retired cert cannot be selected.
     */
    private function focusOptions(): array
    {
        return PassimarkCertificationTrack::query()
            ->where('is_active', true)
            ->orderBy('region')
            ->orderBy('title')
            ->get(['cert_key', 'title', 'region'])
            ->map(fn (PassimarkCertificationTrack $track) => [
                'cert_key' => $track->certKey(),
                'title' => $track->title,
                'region' => $track->region ?: 'General',
            ])
            ->sortBy([['region', 'asc'], ['title', 'asc']])
            ->values()
            ->all();
    }

    /** Persist the chosen focus into the users.preferences json column. */
    public function saveFocus(Request $request): \Illuminate\Http\RedirectResponse
    {
        $active = PassimarkCertificationTrack::query()
            ->where('is_active', true)
            ->pluck('cert_key')
            ->map(fn ($key) => strtolower((string) $key))
            ->all();

        $validated = $request->validate([
            'cert_key' => ['required', 'string', Rule::in($active)],
        ], [
            'cert_key.in' => 'That certification is not available. Pick one from the list.',
        ]);

        $user = Auth::user();
        $preferences = $user->preferences ?? [];
        $preferences['focus'] = mb_strtolower($validated['cert_key']);
        $user->preferences = $preferences;
        $user->save();

        return redirect()->route('passimark.funnel.permissions');
    }

    /** Rung 6 — Permission prime (skippable). */
    public function permissions(): \Inertia\Response
    {
        $this->ensureEnrolled();

        return Inertia::render('Passimark/Funnel/Permissions', [
            'funnel' => [
                'step' => 'permissions',
                'next' => route('dashboard'),
                'submit' => route('passimark.funnel.permissions.complete'),
                'ladder' => self::LADDER,
            ],
        ]);
    }

    /**
     * Rung 7 — close out the first run. Flipping this flag is what stops AuthController from
     * bouncing a returning learner back into the ladder on every subsequent login.
     */
    public function completeFunnel(): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        $preferences = $user->preferences ?? [];
        $preferences['funnel_completed'] = true;
        $user->preferences = $preferences;
        $user->save();

        return redirect()->route('dashboard');
    }

    /** True once the learner has finished the post-login rungs. */
    public static function hasCompletedFunnel(?object $user): bool
    {
        return (bool) ($user->preferences['funnel_completed'] ?? false);
    }
}
