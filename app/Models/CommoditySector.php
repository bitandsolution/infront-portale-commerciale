<?php

namespace App\Models;

use Database\Factories\CommoditySectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Commodity sector of a client (M05 F-05; D-075, D-357): classification and statistics, never an
 * exclusivity constraint (RB-05, D-022). Deactivated, never deleted (RB-08).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $requires_free_text
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CommoditySector extends Model
{
    /** @use HasFactory<CommoditySectorFactory> */
    use HasFactory;

    /** Code of the sector that asks for a free text on the client (RB-04). */
    public const OTHER = 'ALTRO_VARIE';

    protected $guarded = ['id'];

    /**
     * Defaults of the columns, also before the first save (audit of a new sector).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'requires_free_text' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_free_text' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
