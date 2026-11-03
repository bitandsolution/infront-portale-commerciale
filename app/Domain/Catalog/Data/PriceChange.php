<?php

namespace App\Domain\Catalog\Data;

/**
 * What a change of a cell of a price list did (D-333): a new price, a changed price, a price removed
 * (the cell is "not available" again, D-190) or nothing, when the value was the same.
 */
enum PriceChange: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Removed = 'removed';
    case Unchanged = 'unchanged';
}
