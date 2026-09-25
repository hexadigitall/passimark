<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Curriculum;
use App\Http\Controllers\PassimarkFunnelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended($this->landingFor(Auth::user()));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Where a successful sign-in lands. A learner who has not finished the post-login rungs
     * continues at rung 5 (focus) so the ladder is actually walked end to end; once
     * permissions has been completed, returning learners go straight to their dashboard.
     * Staff never enter the learner funnel.
     */
    private function landingFor(?User $user): string
    {
        if ($user && in_array($user->role, ['admin', 'instructor'], true)) {
            return route('dashboard');
        }

        if ($user && ! PassimarkFunnelController::hasCompletedFunnel($user)) {
            return route('passimark.funnel.focus');
        }

        return route('dashboard');
    }

    /**
     * Show registration form
     */
    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle registration
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'student',
        ]);

        Auth::login($user);

        Curriculum::enrollFirstSteps($user);

        return redirect()->route($this->landingFor($user) === route('dashboard')
            ? 'dashboard'
            : 'passimark.funnel.focus');
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
