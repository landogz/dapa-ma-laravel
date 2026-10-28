<?php

namespace App\Providers;

use App\Services\PasswordResetService;
use App\Support\Password\PasswordPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
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
        Password::defaults(static fn () => PasswordPolicy::rule());

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            /** @var PasswordResetService $service */
            $service = app(PasswordResetService::class);

            return $service->buildResetUrl((string) $notifiable->getEmailForPasswordReset(), $token);
        });
    }
}
