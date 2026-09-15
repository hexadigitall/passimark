<?php

namespace App\Http\Middleware;

use App\Models\PassimarkCertificationTrack;
use App\Models\PassimarkProgress;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'role'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            // v4 worldwide: region nav + learner ability estimate for the shell.
            'regions' => fn () => PassimarkCertificationTrack::query()
                ->selectRaw('region, COUNT(*) as cert_count')
                ->whereNotNull('region')
                ->groupBy('region')
                ->orderBy('region')
                ->get()
                ->map(fn ($region) => ['name' => $region->region, 'count' => (int) $region->cert_count])
                ->values(),
            'ability' => [
                'theta' => fn () => $request->user()
                    ? (float) (PassimarkProgress::where('user_id', $request->user()->id)->max('ability_theta') ?? 0.0)
                    : null,
            ],
        ]);
    }
}
