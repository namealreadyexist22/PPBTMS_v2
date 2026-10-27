<?php

namespace App\Http\Middleware\BackEnd;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatePortalUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
   public function handle(Request $request, Closure $next): Response
    {
        // If the user is NOT logged in, block them and redirect to login page with a flash message
        if (!Auth::check()) {
            // A page's background request (a modal, a list redrawn in place) after the session ran out:
            // say so, so the page sends the user to the login page instead of showing it inside itself
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Your session has expired. Please log in again.',
                    'login'   => route('auth.login'),
                ], 401);
            }

            return redirect()
                ->route('auth.login')
                ->with('error', 'Access denied. Please log in to your account first.');
        }

        // If authenticated, allow the request to proceed to the controller
        return $next($request);
    }
}
