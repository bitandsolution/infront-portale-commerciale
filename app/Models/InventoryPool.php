<?php

namespace App\Models;

use Database\Factories\InventoryPoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inventory pool (M03 F-04, M04 §4; D-046): FILA_1 and FILA_2 active, CORNER_BASSI and
 * SOTTO_TRIBUNA foreseen and not active. Capacities in seconds per match arrive with F2-04. From the
 * system data only (D-332): read-only on the Values page (D-331).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $visibility_type_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read VisibilityType $visibilityType
 */
final class InventoryPool extends Model
{
    /** @use HasFactory<InventoryPoolFactory> */
    use HasFactory;

    protected $table = 'inventory_pools';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VisibilityType, $this>
     */
    public function visibilityType(): BelongsTo
    {
        return $this->belongsTo(VisibilityType::class);
    }
}
