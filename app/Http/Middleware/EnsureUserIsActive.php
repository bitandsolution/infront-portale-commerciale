<?php

namespace App\Http\Middleware;

use App\Domain\Audit\Services\AuditLog;
use App\Enums\AccessReason;
use App\Enums\AuditAction;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated user loses access immediately (M01 §5, RB-05): his session is closed at the next
 * request and he is sent back to the login page with a message. Gate::before denies him every
 * permission as well, as a second guard.
 *
 * Audit (D-128, D-283): access.session_closed with reason "deactivated", written before the logout.
 */
class EnsureUserIsActive
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            $this->audit->access(AuditAction::SessionClosed, $user, reason: AccessReason::Deactivated->value);
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->with('toast', [
                'id' => (string) Str::uuid(),
                'type' => 'error',
                'message' => __('ui.auth.deactivated'),
            ]);
        }

        return $next($request);
    }
}
