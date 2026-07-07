<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only users browse; they don't change. Every mutating request is
 * turned away here, so no controller needs to remember to check. Their
 * own profile and logout stay available.
 */
class PreventReadOnlyWrites
{
    private const ALLOWED_ROUTES = ['logout', 'profile.update', 'profile.destroy'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && ! $user->canWrite()
            && ! $request->isMethodSafe()
            && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)
        ) {
            return $request->header('X-Inertia')
                ? back()->with('error', 'Your account is read-only — changes are disabled.')
                : abort(403, 'Read-only account.');
        }

        return $next($request);
    }
}
