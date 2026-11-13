<?php

namespace App\Models;

use App\Domain\Content\Data\DocumentSelection;
use App\Enums\DocumentMode;
use App\Models\Concerns\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Group of variants of a proposal (D-396; slice DM): the proposals A…J of one agent for the same
 * client and the same season, presented in one document. Every variant is a complete proposal with
 * its own number, dates, clubs and total; a single proposal is a group with one variant. Client and
 * season of the group follow those of its variants (SaveDraft keeps them aligned). The agent sees
 * only his own groups (HasOwner, column agent_id). The slides chosen for the document (mode and
 * excluded slides, D-418, D-419) are the working choice of the group, copied on the frozen versions
 * at the approval, like its slides composed with blocks (DM-07, D-399).
 *
 * @property int $id
 * @property int $agent_id
 * @property int|null $client_id
 * @property int|null $season_id
 * @property DocumentMode $document_mode
 * @property list<int>|null $excluded_slide_ids
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $agent
 * @property-read Client|null $client
 * @property-read Season|null $season
 * @property-read Document|null $currentDocument
 */
final class ProposalGroup extends Model
{
    use HasOwner;

    protected $guarded = ['id'];

    /**
     * Defaults of the columns, also before the first save.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'document_mode' => 'complete',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_mode' => DocumentMode::class,
            'excluded_slide_ids' => 'array',
        ];
    }

    /**
     * The working choice of the slides of the document (D-419).
     */
    public function documentSelection(): DocumentSelection
    {
        return DocumentSelection::fromColumns($this->document_mode, $this->excluded_slide_ids);
    }

    /** Same owner column as the proposals (D-376). */
    public function ownerColumn(): string
    {
        return 'agent_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * The variants, in the order of their letter.
     *
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->orderBy('variant_letter');
    }

    /**
     * The slides composed with blocks (D-399; DM-07), in the order they were added.
     *
     * @return HasMany<ProposalCustomSlide, $this>
     */
    public function customSlides(): HasMany
    {
        return $this->hasMany(ProposalCustomSlide::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The current document of the group (D-413; DM-04): the last generation not replaced.
     *
     * @return HasOne<Document, $this>
     */
    public function currentDocument(): HasOne
    {
        return $this->hasOne(Document::class, 'proposal_group_id')
            ->whereNull('superseded_at')
            ->where('format', Document::FORMAT_PDF)
            ->where('language', Document::LANGUAGE);
    }

    /**
     * Every document of the group, the most recent first (D-205: the replaced ones stay).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'proposal_group_id')->orderByDesc('id');
    }
}
