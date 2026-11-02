<?php

namespace App\Models;

use Database\Factories\HospitalityVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Hospitality variant (M03 F-09; D-047): PREMIUM and BASE. Code from the system data (D-332); name,
 * order and active changed by the admin from the Values page (D-331).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class HospitalityVariant extends Model
{
    /** @use HasFactory<HospitalityVariantFactory> */
    use HasFactory;

    protected $table = 'hospitality_variants';

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
}
