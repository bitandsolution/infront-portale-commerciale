<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Users\Actions\ActivateAccount;
use App\Domain\Users\Actions\CompletePasswordReset;
use App\Domain\Users\Services\AccessLinks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AccessLinkRequest;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Pages of the access links sent by e-mail (M01 §5; D-277…D-280), for guests only.
 *
 * - Activation (new user, or second factor reset by the admin): one page with the new password
 *   and the setup of the second factor, saved together when the code is right (D-278). The TOTP
 *   key stays in the session, encrypted, so reloading the page keeps the same QR code; nothing is
 *   written before the end. Then the recovery codes, once, and the portal.
 * - New password (reset started by the admin): new password confirmed with the code of the
 *   authenticator app (D-279). Then the portal.
 *
 * An expired, used or unknown link shows the same page, which never says whether an account exists.
 */
class AccessLinkController extends Controller
{
    private const SECRET_KEY = 'access_link.secret';

    public function __construct(
        private readonly AccessLinks $links,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    public function showActivation(Request $request, string $token): Response
    {
        $user = $this->links->userFor($token);

        if ($user === null || $user->two_factor_confirmed_at !== null) {
            return $this->invalid();
        }

        $secret = $this->pendingSecret($request, $user);

        return Inertia::render('Auth/Activate', [
            'firstName' => $user->first_name,
            'account' => $user->email,
            'renewal' => $user->activated_at !== null,
            'qrCode' => $this->qrCode($user, $secret),
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'submitUrl' => route('access.activation.store', ['token' => $token]),
        ]);
    }

    public function storeActivation(AccessLinkRequest $request, string $token, ActivateAccount $activate): RedirectResponse
    {
        $user = $this->links->userFor($token);
        $secret = $user !== null ? $this->storedSecret($request, $user) : null;

        if ($user === null || $secret === null) {
            return redirect()->route('access.activation', ['token' => $token]);
        }

        $user = $activate->handle(
            $token,
            $request->string('password')->toString(),
            $secret,
            $request->string('code')->toString(),
        );

        $request->session()->forget(self::SECRET_KEY);
        $this->signIn($request, $user);

        // The 8 recovery codes, once (TwoFactorSetupController).
        $request->session()->flash(TwoFactorSetupController::SHOW_RECOVERY_CODES, true);

        return redirect()->route('two-factor.recovery-codes');
    }

    public function showReset(string $token): Response
    {
        $user = $this->links->userFor($token);

        if ($user === null || $user->two_factor_confirmed_at === null) {
            return $this->invalid();
        }

        return Inertia::render('Auth/ResetPassword', [
            'firstName' => $user->first_name,
            'account' => $user->email,
            'submitUrl' => route('access.reset.store', ['token' => $token]),
        ]);
    }

    public function storeReset(AccessLinkRequest $request, string $token, CompletePasswordReset $complete): RedirectResponse
    {
        $user = $complete->handle(
            $token,
            $request->string('password')->toString(),
            $request->string('code')->toString(),
        );

        $this->signIn($request, $user);

        return redirect()->to(PortalPages::url('dashboard'))
            ->with('toast', Toast::success(Text::get('users.toasts.password_set')));
    }

    private function invalid(): Response
    {
        return Inertia::render('Auth/AccessLinkInvalid', ['loginUrl' => route('login')]);
    }

    /**
     * Key shown on the page, created at the first visit and kept (encrypted) in the session.
     */
    private function pendingSecret(Request $request, User $user): string
    {
        $secret = $this->storedSecret($request, $user);

        if ($secret === null) {
            $secret = $this->twoFactor->generateSecretKey();
            $request->session()->put(self::SECRET_KEY, [
                'user' => $user->id,
                'secret' => Crypt::encryptString($secret),
            ]);
        }

        return $secret;
    }

    private function storedSecret(Request $request, User $user): ?string
    {
        $stored = $request->session()->get(self::SECRET_KEY);

        if (! is_array($stored) || ($stored['user'] ?? null) !== $user->id || ! is_string($stored['secret'] ?? null)) {
            return null;
        }

        return Crypt::decryptString($stored['secret']);
    }

    /**
     * QR code of a key not saved yet: Fortify draws it from the user's encrypted secret, so a copy
     * of the user that is never saved carries the key.
     */
    private function qrCode(User $user, string $secret): string
    {
        $preview = $user->replicate();
        $preview->two_factor_secret = Fortify::currentEncrypter()->encrypt($secret);

        return 'data:image/svg+xml;base64,'.base64_encode($preview->twoFactorQrCodeSvg());
    }

    /**
     * Password and second factor are proved: the user enters the portal with a new session.
     */
    private function signIn(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }
}
