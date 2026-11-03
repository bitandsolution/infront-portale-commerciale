<?php

namespace App\Models;

use Database\Factories\VisibilityTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Visibility type (M03 F-01; D-021): CLASSICA active, VIRTUAL not used today. From the system data only
 * (D-332): read-only on the Values page (D-331).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class VisibilityType extends Model
{
    /** @use HasFactory<VisibilityTypeFactory> */
    use HasFactory;

    protected $table = 'visibility_types';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
