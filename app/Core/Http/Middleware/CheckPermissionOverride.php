<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckPermissionOverride
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessPermission($permission)) {
            throw new AccessDeniedHttpException("You don't have permission to do that.");
        }

        return $next($request);
    }
}
