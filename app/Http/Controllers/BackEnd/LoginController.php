<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{

    public function showLoginForm()
    {
        return view('BackEnd.auth.content.login');
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function login(Request $request)
    {
        // 1. Run standard inline validation looking at the generic 'username' field name from the UI
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. Check for rate limiting
        $this->ensureIsNotRateLimited($request);

        $loginValue = $request->input('username');

        // Dynamically detect whether they provided an email address or a standard username string
        $field = filter_var($loginValue, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field         => $loginValue,
            'password'     => $request->input('password'),
            'is_activated' => 1,
        ];

        // 3. Attempt authentication using the dynamically mapped credentials
        if (Auth::attempt($credentials, $request->boolean('remember'))) {

            if ($request->boolean('remember')) {
                cookie()->queue('saved_username', $loginValue, 43200); // 30 days
            } else {
                cookie()->queue(cookie()->forget('saved_username'));
            }

            return $this->handleSuccessfulLogin($request);
        }

        // 4. Increment throttle on failure
        RateLimiter::hit($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => trans('auth.failed'),
        ]);
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('username')).'|'.$request->ip());
    }

    /**
    * Handle the callback returned from Google (Lookup Only).
    */
    public function handleGoogleCallback(Request $request)
    {
        // 1. Enforce Rate Limiting for Google attempts
        if (RateLimiter::tooManyAttempts($this->googleThrottleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->googleThrottleKey($request));

            // Dynamic fallback redirection depending on whether they are logged in or guest
            $route = Auth::check() ? 'main.profile' : 'auth.login';
            return redirect()->route($route)->withErrors([
                'username' => "Too many login attempts. Please try again in {$seconds} seconds."
            ]);
        }

        // 2. Attempt to catch the Socialite User payload
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            RateLimiter::hit($this->googleThrottleKey($request));
            $route = Auth::check() ? 'main.profile' : 'auth.login';
            return redirect()->route($route)->withErrors([
                'username' => 'Google authentication failed. Please try again.'
            ]);
        }

        // =========================================================================
        // CONDITION A: USER IS ALREADY LOGGED IN (Linking from Settings/Profile page)
        // =========================================================================
        if (Auth::check()) {
            $currentUser = Auth::user();

            // Bind the Google ID to the authenticated user profile
            $currentUser->google_id = $googleUser->getId();
            $currentUser->save();

            RateLimiter::clear($this->googleThrottleKey($request));

            return redirect()
                ->route('app.main.profile')
                ->with('success', 'Your Google account has been linked successfully!');
        }

        // =========================================================================
        // CONDITION B: GUEST IS LOGGING IN (Authenticating from public login page)
        // =========================================================================

        // First try finding them by google_id if they linked it previously, otherwise fallback to matching email
        $user = \App\Models\User::query()
            ->where('is_activated', 1)
            ->where(function($query) use ($googleUser) {
                $query->where('google_id', $googleUser->getId())
                    ->orWhere('email', $googleUser->getEmail());
            })
            ->first();

        // 4. Fail if user does not exist in the system
        if (!$user) {
            RateLimiter::hit($this->googleThrottleKey($request));
            return redirect()->route('auth.login')->withErrors([
                'username' => 'Your Google Account is not registered or linked with an active account. Please use your standard credentials or contact MIS.'
            ]);
        }

        // If they logged in by matching email but didn't have a google_id stored yet, link it now
        if (empty($user->google_id)) {
            $user->google_id = $googleUser->getId();
            $user->save();
        }

        // 5. Success! Clear rate limits
        RateLimiter::clear($this->googleThrottleKey($request));

        // 6. Log the user into your default session
        Auth::login($user, false);

        // 7. Reuse your existing IP logging, session regeneration, and welcome flash message logic
        return $this->handleSuccessfulLogin($request);
    }

    protected function handleSuccessfulLogin(Request $request)
    {
        RateLimiter::clear($this->throttleKey($request));

        $user = Auth::user();
        $user->last_login_ip = $request->ip();
        $user->save();

        $request->session()->regenerate();

        $firstName = ucwords(strtolower($user->fname ?? 'User'));
        $welcomeMessage = "Welcome back, {$firstName}! Logged in successfully.";

        return redirect()
            ->route('app.main.home')
            ->with('success', $welcomeMessage);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('auth.login')
            ->with('success', 'You have been logged out.');
    }

        // Add this helper method to your controller for Google throttling
    protected function googleThrottleKey(Request $request): string
    {
        return 'google_login|' . $request->ip();
    }
}
