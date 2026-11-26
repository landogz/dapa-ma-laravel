<?php

namespace App\Providers;

use App\Services\PasswordResetService;
use App\Support\Password\FastNotPwnedVerifier;
use App\Support\Password\PasswordPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->rebindUncompromisedVerifier();

        Password::defaults(static fn () => PasswordPolicy::rule());

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            /** @var PasswordResetService $service */
            $service = app(PasswordResetService::class);

            return $service->buildResetUrl((string) $notifiable->getEmailForPasswordReset(), $token);
        });

        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth-verify', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            $userId = (string) ($request->user()?->getKey() ?? '');

            return Limit::perMinute(8)->by($email.'|'.$userId.'|'.$request->ip());
        });

        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }

    /**
     * Replace Laravel's deferred 30s HIBP verifier with a short connect/timeout
     * implementation so mobile register cannot hang when DNS to HIBP fails.
     */
    private function rebindUncompromisedVerifier(): void
    {
        $this->app->make(UncompromisedVerifier::class);
        $this->app->forgetInstance(UncompromisedVerifier::class);

        $this->app->singleton(UncompromisedVerifier::class, function ($app) {
            $timeout = max(1, (int) config('services.haveibeenpwned.timeout', 3));
            $connect = max(1, (int) config('services.haveibeenpwned.connect_timeout', 2));

            return new FastNotPwnedVerifier(
                $app->make(HttpFactory::class),
                $timeout,
                $connect,
            );
        });
    }
}
