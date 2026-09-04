<?php

namespace App\Http\Middleware;

use App\Models\Contracts\AuditableRecord;
use App\Services\AuditTrail;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes a "viewed" audit entry whenever a screen that opens part of a
 * patient's chart is rendered.
 *
 * Any route-bound model that carries the Auditable trait and belongs to a
 * patient counts: the patient profile, an encounter, an admission, a lab
 * order, a bill, a claim, a pregnancy. Redirects and Inertia partial reloads
 * (polling a queue, refreshing one prop) are not views of a record.
 */
class LogPatientRecordAccess
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isRecordView($request, $response)) {
            foreach ($request->route()?->parameters() ?? [] as $parameter) {
                if ($parameter instanceof Model
                    && $parameter instanceof AuditableRecord
                    && $parameter->isPatientRecord()) {
                    $this->audit->recordAccess($parameter);
                }
            }
        }

        return $response;
    }

    private function isRecordView(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $request->user() !== null
            && $response->isSuccessful()
            && ! $request->hasHeader('X-Inertia-Partial-Data');
    }
}
