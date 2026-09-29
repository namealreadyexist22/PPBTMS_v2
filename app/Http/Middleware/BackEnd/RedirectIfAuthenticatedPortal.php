<?php

namespace App\Http\Middleware\BackEnd;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticatedPortal
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is ALREADY logged in, intercept the request and redirect them away from guest pages
        if (Auth::check()) {
            $user = Auth::user();
            // Example Role Split: Check for pending approval
            if ($user->roletype === 0) {
                session()->flash('warning', 'Your account profile status is still pending validation.');
                return redirect()->route('app.main.home');
            }

             if (in_array($user->roletype, [2, 3])) {
                session()->flash('info', 'Admin panel session initialized.');
                return redirect()->route('app.main.home');
            }

            // Standard successful dashboard entry notification
            session()->flash('info', 'You are already signed into your active session.');
            return redirect()->route('app.main.home');
        }

        // If they are not logged in, allow them to view the login page normally
        return $next($request);
    }
}
