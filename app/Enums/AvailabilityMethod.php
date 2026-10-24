<?php

namespace App\Enums;

/**
 * Method of the LED availability calculation (M04 F-11, RB-12; D-062), stored in the setting
 * "availability_method".
 *
 * - seasonal: a commitment holds for the whole season, whatever the contract period (active);
 * - per_match: a commitment holds only for the matches inside its period (future: the data are
 *   already prepared by the period of each commitment, D-192, but the calculation is not built).
 */
enum AvailabilityMethod: string
{
    case Seasonal = 'seasonal';
    case PerMatch = 'per_match';

    /**
     * Italian label (lang/it/settings.php).
     */
    public function label(): string
    {
        $label = __('settings.availability_methods.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
