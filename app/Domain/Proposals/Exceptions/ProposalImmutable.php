<?php

namespace App\Domain\Proposals\Exceptions;

use LogicException;

/**
 * A frozen version of a proposal, its club rows or their seats (D-198, M03-CA6), or an entry of the
 * history (D-199), were about to be changed or deleted. First line of defence in the models; the
 * triggers of the database are the second. A programming error, never a message for the user.
 */
final class ProposalImmutable extends LogicException
{
    public static function version(int $versionId): self
    {
        return new self("Proposal version {$versionId} is frozen: it is never changed nor deleted.");
    }

    public static function history(): self
    {
        return new self('The history of a proposal only accepts inserts.');
    }
}
