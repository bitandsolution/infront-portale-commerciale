<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

/**
 * Mandatory two-factor setup (D-136, M01-CA1; progettazione-tecnica §5.4.2).
 *
 * Until the user confirms a code from the authenticator app, every page leads here
 * (App\Http\Middleware\EnsureTwoFactorIsConfigured). The secret and the 8 recovery codes are
 * created by Fortify (encrypted in users); the recovery codes are shown once, right after
 * the confirmation.
 */
class TwoFactorSetupController extends Controller
{
    /** Session flag set by the confirmation: the next page shows the recovery codes once. */
    public const SHOW_RECOVERY_CODES = 'two_factor.show_recovery_codes';

    public function show(Request $request, EnableTwoFactorAuthentication $enable): Response|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->to(PortalPages::url('dashboard'));
        }

        // Creates the secret only the first time: reloading the page keeps the same QR code.
        $enable($user);

        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

        return Inertia::render('Auth/TwoFactorSetup', [
            'qrCode' => 'data:image/svg+xml;base64,'.base64_encode($user->twoFactorQrCodeSvg()),
            'secret' => is_string($secret) ? trim(chunk_split($secret, 4, ' ')) : '',
            'account' => $user->email,
            'confirmUrl' => route('two-factor.confirm'),
            'logoutUrl' => route('logout'),
        ]);
    }

    public function store(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $user = $this->user($request);

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->to(PortalPages::url('dashboard'));
        }

        $validated = $request->validate(['code' => ['required', 'string']]);

        try {
            $confirm($user, preg_replace('/\s+/', '', $validated['code']));
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => [__('auth.two_factor.invalid_code')]]);
        }

        // The account now has its second factor: new session identifier for the new privilege.
        $request->session()->regenerate();
        $request->session()->flash(self::SHOW_RECOVERY_CODES, true);

        return redirect()->route('two-factor.recovery-codes');
    }

    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->two_factor_confirmed_at === null) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get(self::SHOW_RECOVERY_CODES) !== true) {
            return redirect()->to(PortalPages::url('dashboard'));
        }

        return Inertia::render('Auth/RecoveryCodes', [
            'codes' => $user->recoveryCodes(),
            'continueUrl' => PortalPages::url('dashboard'),
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
