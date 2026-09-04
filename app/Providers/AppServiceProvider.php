<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Services\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One trail per request, so pausing it (bulk imports) is request-scoped.
        $this->app->scoped(AuditTrail::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->auditAuthentication();
    }

    /**
     * Sign-ins, sign-outs and failed attempts go into the audit trail so a
     * patient-record access can always be tied back to a session.
     */
    protected function auditAuthentication(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            app(AuditTrail::class)->record(AuditLog::ACTION_LOGIN, $event->user instanceof Model ? $event->user : null);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(AuditTrail::class)->record(AuditLog::ACTION_LOGOUT, $event->user instanceof Model ? $event->user : null);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            // Only the identifier is kept; the submitted password never is.
            $identifier = $event->credentials[config('fortify.username', 'email')]
                ?? $event->credentials['email']
                ?? $event->credentials['username']
                ?? 'unknown';

            app(AuditTrail::class)->record(
                AuditLog::ACTION_LOGIN_FAILED,
                $event->user instanceof Model ? $event->user : null,
                label: "Failed sign-in for {$identifier}",
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Resources are handed straight to Inertia as page props.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
