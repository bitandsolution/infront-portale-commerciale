<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Section of the slides (M08 §4.1, RB-03; D-406): fixed, from the system data
 * (ContentSystemDataSeeder), without a management page. The document is ordered by section, then by
 * the order of the slide in the section.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SlideSection extends Model
{
    /** Section of the closing slide, kept in the "summary only" document (D-397, D-405). */
    public const CLOSING = 'CLOSING';

    /** Default section of a slide composed with blocks: at its end, right before the recap (D-429). */
    public const BRAND_EXPOSURE = 'BRAND_EXPOSURE';

    /**
     * Sections a slide composed with blocks can close (D-429): those before the recap.
     *
     * @var list<string>
     */
    public const CUSTOM_SLIDE_SECTIONS = ['INTRO', 'CLUB', 'PARTNERSHIP', 'BENEFIT', 'BRAND_EXPOSURE'];

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
