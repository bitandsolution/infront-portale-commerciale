<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An image uploaded for the slides composed with blocks of a group (D-399; DM-07): who may see the
 * group may see the image (StoredFilePolicy), also after the slide is changed or deleted. Written by
 * AttachCustomSlideImage, never changed.
 *
 * @property int $id
 * @property int $proposal_group_id
 * @property int $stored_file_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProposalGroup $group
 * @property-read StoredFile $file
 */
final class ProposalGroupFile extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<ProposalGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProposalGroup::class, 'proposal_group_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }
}
