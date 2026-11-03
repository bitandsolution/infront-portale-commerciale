<?php

namespace App\Enums;

/**
 * Why a commitment was released (D-192), stored in inventory_commitments.release_reason (C8):
 * superseded by the commitments of a new version of the approved proposal, or cancelled with the
 * proposal. Written by F5; in F2 only the tests release commitments.
 */
enum CommitmentReleaseReason: string
{
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
