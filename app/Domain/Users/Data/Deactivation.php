<?php

namespace App\Domain\Users\Data;

use App\Models\User;

/**
 * Outcome of DeactivateUser: the deactivated user and, for an agent, how many of his clients were
 * reassigned in the same transaction, and to whom (M01-CA4, D-360).
 */
final readonly class Deactivation
{
    public function __construct(
        public User $user,
        public int $reassignedClients = 0,
        public ?User $clientsOwner = null,
    ) {}
}
