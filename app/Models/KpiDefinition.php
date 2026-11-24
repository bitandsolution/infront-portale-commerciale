<?php

namespace App\Models;

use App\Enums\KpiGroup;
use App\Enums\KpiUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Definition of a KPI (M08 §5; D-108, D-203, D-490): the 13 KPI of D-108 from the system data
 * (ContentSystemDataSeeder), with a stable code, without a management page. network_aggregate marks
 * the KPI summed in the network document (fanbase, audience, attendance, the five social networks;
 * D-490: to be confirmed with Infront before F7).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property KpiGroup $group
 * @property KpiUnit $unit
 * @property bool $network_aggregate
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class KpiDefinition extends Model
{
    protected $guarded = ['id'];

    /**
     * Active definitions in the order of the pages: club group first, then digital.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveOrdered(Builder $query): void
    {
        $query->where('is_active', true)
            ->orderByRaw("CASE `group` WHEN 'club' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => KpiGroup::class,
            'unit' => KpiUnit::class,
            'network_aggregate' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
