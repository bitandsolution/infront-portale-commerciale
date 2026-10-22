<?php

namespace App\Domain\Users\Data;

use App\Enums\Role;

/**
 * Data of a new user (M01 §5): name, surname, e-mail (username), role, optional phone.
 * The password is set directly only by the first-admin command; from PR-04 the admin creates
 * users without a password and the activation link sets it (D-209).
 */
final readonly class NewUserData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public Role $role,
        public ?string $password = null,
        public ?string $phone = null,
    ) {}
}
