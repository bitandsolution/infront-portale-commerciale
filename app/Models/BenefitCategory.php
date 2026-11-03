<?php

namespace App\Models;

use Database\Factories\BenefitCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Category of the partnership benefits (M03 F-08, §4): the 8 categories of the decks. Code from the
 * system data (D-332); name and order changed by the admin from the Values page (D-331). The texts
 * of the benefits are optional (D-117) and arrive with F2-03.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class BenefitCategory extends Model
{
    /** @use HasFactory<BenefitCategoryFactory> */
    use HasFactory;

    protected $table = 'benefit_categories';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
