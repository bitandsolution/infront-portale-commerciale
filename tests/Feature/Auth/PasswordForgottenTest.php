<?php

use App\Enums\AccessLinkType;
use App\Enums\AccessReason;
use App\Enums\AuditAction;
use App\Http\Controllers\Auth\PasswordForgottenController;
use App\Mail\AccessLinkMail;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Self-service reset of the password from the login page (M01 §5; D-279; PR-08):
| - the same answer for every address (no enumeration of the accounts);
| - a link only for an active, activated user with the second factor; the current password keeps
|   working until the new one is set, confirmed with the code of the app;
| - at most 5 requests in 15 minutes from an IP address, 3 links per address in an hour;
| - every request in the audit (access.password_reset_requested), with the reason when no link.
*/

const FORGOT_OLD_PASSWORD = 'Password-Attuale-Stadio-2027!';
const FORGOT_NEW_PASSWORD = 'Password-Nuova-Curva-2027!';

beforeEach(function () {
    Mail::fake();
    fakePasswordArchive();
});

function forgotRequest(string $email, string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from(route('password.request'))
        ->post(route('password.email'), ['email' => $email]);
}

it('D-279: the login page links to "Password dimenticata?"', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->component('Auth/Login')
        ->where('forgotUrl', route('password.request')));

    $this->get(route('password.request'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/ForgotPassword')
        ->where('submitUrl', route('password.email'))
        ->where('loginUrl', route('login'))
        ->where('sent', false));
});

it('D-279: answers the same for every address and sends the link only to an active user with the second factor', function () {
    $eligible = User::factory()->agent()->create();
    $deactivated = User::factory()->agent()->inactive()->create();
    $pending = User::factory()->agent()->withoutTwoFactor()->create(['activated_at' => null]);
    $withoutTwoFactor = User::factory()->agent()->withoutTwoFactor()->create();

    foreach ([$eligible->email, 'nessuno@example.test', $deactivated->email, $pending->email, $withoutTwoFactor->email] as $email) {
        forgotRequest($email)
            ->assertRedirect(route('password.request'))
            ->assertSessionHas(PasswordForgottenController::SENT_KEY, true)
            ->assertSessionHasNoErrors();

        $this->get(route('password.request'))->assertInertia(fn (Assert $page) => $page->where('sent', true));
    }

    Mail::assertQueuedCount(1);
    expect(sentAccessLink($eligible->email, AccessLinkType::PasswordForgotten))->toStartWith(url('nuova-password').'/');
});

it('D-279: the current password keeps working until the new one is set', function () {
    $agent = User::factory()->agent()->withPassword(FORGOT_OLD_PASSWORD)->create();

    forgotRequest($agent->email);

    expect(Hash::check(FORGOT_OLD_PASSWORD, (string) $agent->fresh()?->password))->toBeTrue();
});

it('D-279: from the link the user sets a new password, confirms it with the app code and enters', function () {
    $agent = User::factory()->agent()->withPassword(FORGOT_OLD_PASSWORD)->create();
    forgotRequest($agent->email);
    $url = sentAccessLink($agent->email, AccessLinkType::PasswordForgotten);

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Auth/ResetPassword')
        ->where('account', $agent->email));

    $this->post($url, ['password' => FORGOT_NEW_PASSWORD, 'password_confirmation' => FORGOT_NEW_PASSWORD, 'code' => totpCode()])
        ->assertRedirect(PortalPages::url('dashboard'));

    $agent->refresh();
    $this->assertAuthenticatedAs($agent);
    expect(Hash::check(FORGOT_NEW_PASSWORD, $agent->password))->toBeTrue();
    expect(soleAuditEvent(AuditAction::UserPasswordReset)->user_id)->toBe($agent->id);

    // Once only.
    $this->post(route('logout'));
    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
});

it('D-277: a new request replaces the previous link', function () {
    $agent = User::factory()->agent()->create();

    forgotRequest($agent->email);
    $first = sentAccessLink($agent->email, AccessLinkType::PasswordForgotten);
    forgotRequest($agent->email);
    $second = sentAccessLink($agent->email, AccessLinkType::PasswordForgotten);

    expect($second)->not->toBe($first);
    $this->get($first)->assertInertia(fn (Assert $page) => $page->component('Auth/AccessLinkInvalid'));
    $this->get($second)->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword'));
});

it('D-279: sends at most 3 links per address in an hour, with the same answer', function () {
    $agent = User::factory()->agent()->create();

    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4'] as $ip) {
        forgotRequest($agent->email, $ip)->assertSessionHas(PasswordForgottenController::SENT_KEY, true);
    }

    Mail::assertQueuedCount(3);
    expect(auditEventsOf(AuditAction::PasswordResetRequested)->pluck('reason')->all())
        ->toBe([null, null, null, AccessReason::Throttled->value]);

    $this->travel(61)->minutes();
    forgotRequest($agent->email, '10.0.0.5');
    Mail::assertQueuedCount(4);
});

it('D-279: accepts at most 5 requests in 15 minutes from the same IP address', function () {
    foreach (range(1, 5) as $index) {
        forgotRequest("indirizzo{$index}@example.test", '10.0.0.9')->assertRedirect(route('password.request'));
    }

    forgotRequest('indirizzo6@example.test', '10.0.0.9')->assertStatus(429);
    expect(auditEventsOf(AuditAction::PasswordResetRequested))->toHaveCount(5);
});

it('D-285: records every request in the audit, with the IP address and the reason when no link was sent', function () {
    $eligible = User::factory()->agent()->create();
    $deactivated = User::factory()->agent()->inactive()->create();
    $pending = User::factory()->agent()->withoutTwoFactor()->create(['activated_at' => null]);
    $withoutTwoFactor = User::factory()->agent()->withoutTwoFactor()->create();

    forgotRequest($eligible->email, '10.0.0.21');
    forgotRequest('NESSUNO@example.test', '10.0.0.22');
    forgotRequest($deactivated->email, '10.0.0.23');
    forgotRequest($pending->email, '10.0.0.24');
    forgotRequest($withoutTwoFactor->email, '10.0.0.25');

    $events = auditEventsOf(AuditAction::PasswordResetRequested);

    expect($events->map(fn ($event): array => [$event->user_id, $event->reason, $event->ip_address, $event->area])->all())->toBe([
        [$eligible->id, null, '10.0.0.21', 'access'],
        [null, AccessReason::UnknownEmail->value, '10.0.0.22', 'access'],
        [$deactivated->id, AccessReason::Deactivated->value, '10.0.0.23', 'access'],
        [$pending->id, AccessReason::NotActivated->value, '10.0.0.24', 'access'],
        [$withoutTwoFactor->id, AccessReason::TwoFactorNotConfigured->value, '10.0.0.25', 'access'],
    ]);
    // The address typed for an unknown account, in lower case, as for a failed login (M10-CA2).
    expect($events[1]->object_label)->toBe('nessuno@example.test');
});

it('D-279: checks only the format of the address', function () {
    forgotRequest('non-è-una-email')->assertSessionHasErrors('email');
    forgotRequest('')->assertSessionHasErrors('email');

    Mail::assertNothingOutgoing();
    expect(auditEventsOf(AuditAction::PasswordResetRequested))->toHaveCount(0);
});

it('M01 §7: the e-mail says who asked and that the current password still works', function () {
    $agent = User::factory()->agent()->create(['first_name' => 'Lucia']);

    forgotRequest($agent->email);

    Mail::assertQueued(AccessLinkMail::class, function (AccessLinkMail $mail) use ($agent): bool {
        $mail->assertHasSubject('Nuova password per il Portale Commerciale Infront');
        $mail->assertSeeInHtml('Ciao Lucia,');
        $mail->assertSeeInHtml('Hai chiesto di scegliere una nuova password');
        $mail->assertSeeInHtml('la tua password attuale continua a funzionare');
        $mail->assertSeeInHtml('48 ore');

        return $mail->hasTo($agent->email);
    });
});
