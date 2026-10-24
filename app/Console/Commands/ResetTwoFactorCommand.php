<?php

namespace App\Console\Commands;

use App\Domain\Users\Actions\ClearTwoFactor;
use App\Models\User;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Unlocks a user without his second factor, from the server (D-276): for whoever has SSH access,
 * also for the only admin, who from the portal nobody else could unlock (the reset in the portal
 * is only for the admin, D-140).
 *
 * Asks for confirmation; with --no-interaction the answer is "no" and nothing changes.
 * The user keeps his password and configures the second factor again at the next login; his open
 * sessions close (EnsureTwoFactorIsUnchanged).
 */
class ResetTwoFactorCommand extends Command
{
    /** @var string */
    protected $signature = 'users:reset-two-factor {email : E-mail of the user}';

    /** @var string */
    protected $description = 'Reset the second factor of a user (asks for confirmation)';

    public function handle(ClearTwoFactor $clearTwoFactor): int
    {
        $argument = $this->argument('email');
        $email = mb_strtolower(trim(is_string($argument) ? $argument : ''));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->line(Text::get('users.reset_two_factor.not_found', ['email' => $email]));

            return self::FAILURE;
        }

        if ($user->two_factor_secret === null && $user->two_factor_confirmed_at === null) {
            $this->line(Text::get('users.reset_two_factor.none', ['email' => $user->email]));

            return self::SUCCESS;
        }

        $this->line(Text::get('users.reset_two_factor.summary', [
            'name' => $user->fullName(),
            'email' => $user->email,
            'role' => $user->role->label(),
        ]));

        if (! $this->input->isInteractive() || ! $this->confirm(Text::get('users.reset_two_factor.confirm'), false)) {
            $this->line(Text::get('users.reset_two_factor.cancelled'));

            return self::FAILURE;
        }

        $clearTwoFactor->handle($user);

        $this->line(Text::get('users.reset_two_factor.done', ['email' => $user->email]));

        return self::SUCCESS;
    }
}
