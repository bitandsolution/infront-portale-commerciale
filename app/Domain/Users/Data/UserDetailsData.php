<?php

namespace App\Domain\Users\Data;

use App\Enums\Role;

/**
 * Data of a user that the admin can change (M01 §5): name, surname, e-mail, phone, role.
 */
final readonly class UserDetailsData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public Role $role,
        public ?string $phone = null,
    ) {}
}
