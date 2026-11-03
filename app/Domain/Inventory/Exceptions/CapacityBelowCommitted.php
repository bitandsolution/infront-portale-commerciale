<?php

namespace App\Domain\Inventory\Exceptions;

use App\Domain\Inventory\Data\ResourceAvailability;
use RuntimeException;

/**
 * The new capacities leave some resources under the committed quantity (M04 RB-10, D-072): allowed,
 * but only after an explicit confirmation (ux-architettura §4.5, "Riduci capacità sotto
 * l'impegnato"). Nothing has been written; the page shows the resources and asks to confirm.
 */
final class CapacityBelowCommitted extends RuntimeException
{
    /**
     * @param  list<ResourceAvailability>  $resources  the resources as they would be after the change
     */
    public function __construct(public readonly array $resources)
    {
        parent::__construct('Capacities under the committed quantity need a confirmation.');
    }
}
