<?php

namespace App\Enums;

/**
 * Why a commitment was released (D-192), stored in inventory_commitments.release_reason (C8):
 * superseded by the commitments of a new version of the approved proposal, or cancelled with the
 * proposal, or released because the client chose another variant of the group (D-410, DM-03b).
 */
enum CommitmentReleaseReason: string
{
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
    case NotChosen = 'not_chosen';
}
