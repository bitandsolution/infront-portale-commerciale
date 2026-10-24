<?php

namespace App\Domain\Audit\Exceptions;

use LogicException;

/**
 * An audit event is never changed nor deleted by the code (RB-A1, D-210). The retention command
 * (audit:prune) works with direct queries on the maintenance connection, inside the limits of the
 * database triggers.
 */
final class AuditEventImmutable extends LogicException
{
    public static function make(): self
    {
        return new self('Audit events are append-only: they cannot be changed or deleted.');
    }
}
