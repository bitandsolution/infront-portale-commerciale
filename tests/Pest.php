<?php

use App\Enums\AccessLinkType;
use App\Mail\AccessLinkMail;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
| Pest configuration. Feature tests run on MariaDB 11.8 (CLAUDE.md §11), each in a transaction.
|
| Concurrency tests (PR-04) need two real database sessions that see each other's committed rows,
| so they cannot run inside the transaction of RefreshDatabase: they truncate the tables instead.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
| Helpers of the login tests (PR-03).
*/

/**
 * Current TOTP code of a key: the code the authenticator app would show now.
 */
function totpCode(string $secret = UserFactory::TOTP_SECRET): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

/*
| Helpers of the user management tests (PR-04).
*/

/**
 * Password::defaults() asks the breached-password archive (k-anonymity): no real request in
 * tests, the archive answers that no password is breached.
 */
function fakePasswordArchive(): void
{
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
}

/**
 * URL of the last access link e-mailed to an address (Mail::fake() must be active).
 */
function sentAccessLink(string $email, ?AccessLinkType $type = null): string
{
    $url = '';

    Mail::assertSent(AccessLinkMail::class, function (AccessLinkMail $mail) use ($email, $type, &$url): bool {
        if (! $mail->hasTo($email) || ($type !== null && $mail->type !== $type)) {
            return false;
        }

        $url = $mail->url;

        return true;
    });

    return $url;
}
