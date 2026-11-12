<?php

namespace App\Enums;

use App\Support\Text;

/**
 * Kind of photo of the simulation of the logo on the LED (D-398), stored in
 * led_mockup_settings.photo_kind (C8), like the photos of the old system (<CLUB>_FILA1,
 * _FILA2, _JUMBO_DOMINATION):
 * - row_1: the 1ª fila free on the photo, one area (format 1ª fila);
 * - row_2: the 2ª fila free, one area (format 2ª fila);
 * - both_rows: both rows free, two areas, upper and lower (formats consuming both rows: Jumbo, the
 *   logo on both rows; Domination, one large logo on both rows with the separator line).
 */
enum LedPhotoKind: string
{
    case Row1 = 'row_1';
    case Row2 = 'row_2';
    case BothRows = 'both_rows';

    /** Codes of the two pools of the rows (CatalogSystemDataSeeder::POOLS). */
    public const POOL_ROW_1 = 'FILA_1';

    public const POOL_ROW_2 = 'FILA_2';

    /**
     * The kind of photo of a LED format from the pools it consumes (D-051): both rows, or one; null
     * for a format on other pools (no photo).
     *
     * @param  list<string>  $poolCodes
     */
    public static function forPools(array $poolCodes): ?self
    {
        $row1 = in_array(self::POOL_ROW_1, $poolCodes, true);
        $row2 = in_array(self::POOL_ROW_2, $poolCodes, true);

        return match (true) {
            $row1 && $row2 => self::BothRows,
            $row1 => self::Row1,
            $row2 => self::Row2,
            default => null,
        };
    }

    /**
     * Areas of the LED to mark on the photo.
     */
    public function areaCount(): int
    {
        return $this === self::BothRows ? 2 : 1;
    }

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        return Text::get('content.led_photo_kinds.'.$this->value);
    }
}
