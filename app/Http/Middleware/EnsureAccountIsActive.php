<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * Blocking sign-in alone would let a member of staff who is already signed
     * in keep working after being deactivated. This ends the session they still
     * hold on their next request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isDeactivated()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort_if($request->expectsJson(), 401, 'Your account has been deactivated.');

        return redirect()->route('login')->withErrors([
            'email' => 'Your account has been deactivated. Contact an administrator.',
        ]);
    }
}
