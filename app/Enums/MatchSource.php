<?php

namespace App\Enums;

/**
 * Origin of a match (D-185), stored in matches.source (C8): typed in the Matches page or written by a
 * calendar import (F1-04).
 */
enum MatchSource: string
{
    case Manual = 'manual';
    case Import = 'import';
}
