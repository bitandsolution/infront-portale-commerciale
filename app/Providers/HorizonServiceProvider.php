<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

/**
 * Access to the Horizon panel (progettazione-tecnica §5.2.7; PR-08): admin only.
 *
 * The routes of the panel already pass through the portal middleware (config/horizon.php: signed
 * in, active, second factor configured, Gate of Ability::QueuesMonitor). This check replaces
 * Horizon's own default, which lets everybody in when the environment is "local" (the VM): the
 * panel shows queued jobs, so no environment is exempt.
 */
class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Horizon::auth(static function (Request $request): bool {
            $user = $request->user();

            return $user instanceof User && Gate::forUser($user)->allows(Ability::QueuesMonitor->value);
        });
    }
}
