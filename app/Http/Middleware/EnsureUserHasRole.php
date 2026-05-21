<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        $roles = array_values(array_filter($roles));

        if ($roles === [] || !$user->roles()->whereIn('role_type', $roles)->exists()) {
            abort(403, '您沒有權限存取此資源');
        }

        return $next($request);
    }
}