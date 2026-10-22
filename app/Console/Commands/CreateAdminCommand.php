<?php

namespace App\Console\Commands;

use App\Domain\Users\Actions\CreateUser;
use App\Domain\Users\Data\NewUserData;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the first admin of the portal (PR-02). Interactive only: the password is typed
 * and never appears in the shell history.
 *
 * Refuses to run when an admin already exists: from PR-04 the admin creates the other users
 * (also other admins) from the portal, with the activation link (D-139, D-209).
 * The two-factor authentication is configured at the first login (PR-03, D-136).
 */
class CreateAdminCommand extends Command
{
    /** @var string */
    protected $signature = 'users:create-admin';

    /** @var string */
    protected $description = 'Create the first admin of the portal (interactive)';

    public function handle(CreateUser $createUser): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error($this->text('users.create_admin.interactive_only'));

            return self::FAILURE;
        }

        if (User::query()->where('role', Role::Admin)->exists()) {
            $this->components->error($this->text('users.create_admin.already_exists'));

            return self::FAILURE;
        }

        $this->components->info($this->text('users.create_admin.intro'));

        $input = [
            'first_name' => text(label: $this->text('users.fields.first_name'), required: true),
            'last_name' => text(label: $this->text('users.fields.last_name'), required: true),
            'email' => mb_strtolower(trim(text(label: $this->text('users.fields.email'), required: true))),
            'password' => password(label: $this->text('users.fields.password'), required: true),
            'password_confirmation' => password(label: $this->text('users.fields.password_confirmation'), required: true),
        ];

        $validator = Validator::make($input, CreateUser::rules(withPassword: true));

        if ($validator->fails()) {
            $this->components->error($this->text('users.create_admin.invalid'));
            $this->components->bulletList($validator->errors()->all());

            return self::FAILURE;
        }

        $admin = $createUser->handle(new NewUserData(
            firstName: $input['first_name'],
            lastName: $input['last_name'],
            email: $input['email'],
            role: Role::Admin,
            password: $input['password'],
        ));

        $this->components->info($this->text('users.create_admin.created', ['email' => $admin->email]));
        $this->line($this->text('users.create_admin.next_steps'));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
