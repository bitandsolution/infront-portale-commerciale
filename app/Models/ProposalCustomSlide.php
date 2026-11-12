<?php

namespace App\Models;

use App\Enums\CustomSlideLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A slide composed with blocks for the document of a group (D-399, D-428…D-432; DM-07): at most
 * three per group, written by the agent on drafts and rejected groups, by admin and manager while
 * the group waits (CreateCustomSlide, SaveCustomSlide, DeleteCustomSlide, AttachCustomSlideImage,
 * with audit). The blocks are the JSON normalized by CustomSlideContent; ApproveGroup copies the
 * slides on the frozen versions (proposal_versions.custom_slides).
 *
 * @property int $id
 * @property int $proposal_group_id
 * @property int $sort_order
 * @property string $title
 * @property CustomSlideLayout $layout
 * @property int $slide_section_id
 * @property int|null $image_file_id
 * @property list<array<string, mixed>> $blocks
 * @property bool $is_included
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProposalGroup $group
 * @property-read SlideSection $section
 */
final class ProposalCustomSlide extends Model
{
    /** D-399: at most three composed slides per group. */
    public const MAX_PER_GROUP = 3;

    protected $guarded = ['id'];

    /**
     * Defaults of the columns, also before the first save.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_included' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'layout' => CustomSlideLayout::class,
            'blocks' => 'array',
            'is_included' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProposalGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProposalGroup::class, 'proposal_group_id');
    }

    /**
     * @return BelongsTo<SlideSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SlideSection::class, 'slide_section_id');
    }
}
