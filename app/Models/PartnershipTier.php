<?php

namespace App\Models;

use Database\Factories\PartnershipTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Partnership tier (M03 F-06; D-050, D-052): LIBERA, TOP, PREMIUM, OFFICIAL, OFFICIAL_2. The free
 * package (is_free_package, the "Libera") has no price, no benefits and no counter (M03 RB-04, M04
 * RB-03). Code and free-package flag from the system data (D-332), never changed from the portal;
 * name, order and active changed by the admin from the Values page (D-331).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_free_package
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PartnershipTier extends Model
{
    /** @use HasFactory<PartnershipTierFactory> */
    use HasFactory;

    protected $table = 'partnership_tiers';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_free_package' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
