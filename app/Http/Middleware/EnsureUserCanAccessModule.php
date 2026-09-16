<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessModule
{
    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('module:laboratory')
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        abort_unless($user && $user->canAccessModule($module), 403);

        return $next($request);
    }
}
