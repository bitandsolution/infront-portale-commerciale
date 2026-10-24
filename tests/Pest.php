<?php

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
| Pest configuration. Feature tests run on MariaDB 11.8 (CLAUDE.md §11), each in a transaction.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

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
