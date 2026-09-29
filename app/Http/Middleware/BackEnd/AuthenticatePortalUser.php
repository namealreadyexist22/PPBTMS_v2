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
            return redirect()
                ->route('auth.login')
                ->with('error', 'Access denied. Please log in to your account first.');
        }

        // If authenticated, allow the request to proceed to the controller
        return $next($request);
    }
}
