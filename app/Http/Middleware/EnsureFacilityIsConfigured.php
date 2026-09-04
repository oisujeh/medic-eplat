<?php

namespace App\Http\Middleware;

use App\Services\FacilitySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacilityIsConfigured
{
    public function __construct(private readonly FacilitySettings $facility) {}

    /**
     * Handle an incoming request.
     *
     * Until the first-run wizard has captured the facility profile, signed-in
     * staff are held at the wizard: administrators are sent to complete it and
     * everyone else to a holding page. Guests pass through so that the
     * administrator can sign in, and signing out is always allowed.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs('setup.*', 'logout') || $this->facility->isConfigured()) {
            return $next($request);
        }

        return redirect()->route(
            $user->canAccessModule('administration') ? 'setup.show' : 'setup.pending',
        );
    }
}
