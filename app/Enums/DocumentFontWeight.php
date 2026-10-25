<?php

namespace App\Enums;

/**
 * The three weights of the document font (D-249; layout-dinamici §2.1): Interstate Regular, Bold
 * and Black, each loaded from its own file with an explicit CSS weight, because the Bold and Black
 * files declare the "Regular" subfamily inside and Chromium would confuse them as system fonts.
 */
enum DocumentFontWeight: int
{
    case Regular = 400;
    case Bold = 700;
    case Black = 900;
}
