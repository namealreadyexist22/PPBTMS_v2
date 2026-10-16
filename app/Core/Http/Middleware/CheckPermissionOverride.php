<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckPermissionOverride
{
    /** perm:manage ppmp, or perm:manage ppmp|menu.division-ppmp when any one of them is enough. */
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = $request->user();
        $allowed = $user && collect(explode('|', $permissions))
            ->contains(fn (string $permission) => $user->canAccessPermission(trim($permission)));

        if (! $allowed) {
            throw new AccessDeniedHttpException("You don't have permission to do that.");
        }

        return $next($request);
    }
}
