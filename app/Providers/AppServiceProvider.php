<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Models\User;
use App\Support\SupportCode;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One support code per request (D-265).
        $this->app->scoped(SupportCode::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuthorization();

        // D-214: at least 12 characters, checked against known breached passwords.
        // Single definition, reused by the first-admin command and, from PR-04, by activation,
        // reset and change of the password.
        Password::defaults(fn (): Password => Password::min(12)->uncompromised());
    }

    /**
     * Area Gates from the M01 §4 matrix (D-215). Entity Policies are discovered by name (App\Policies).
     */
    private function registerAuthorization(): void
    {
        // A deactivated user has no permission at all, whatever the role (RB-05).
        Gate::before(fn (User $user): ?bool => $user->is_active ? null : false);

        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user): bool => $ability->allows($user->role));
        }
    }
}
