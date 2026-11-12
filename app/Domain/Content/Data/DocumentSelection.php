<?php

namespace App\Domain\Content\Data;

use App\Enums\DocumentMode;

/**
 * The slides chosen for the document of a group (D-397, D-418, D-419; DM-05): the mode and the
 * slides excluded by the agent or by who approves. Read from the group while it is worked on, from
 * the frozen version once approved; without a choice (versions frozen before DM-05) the complete
 * document without exclusions.
 */
final readonly class DocumentSelection
{
    /**
     * @param  list<int>  $excludedSlideIds
     */
    public function __construct(
        public DocumentMode $mode = DocumentMode::Complete,
        public array $excludedSlideIds = [],
    ) {}

    /**
     * From the two stored columns (proposal_groups or proposal_versions).
     */
    public static function fromColumns(DocumentMode|string|null $mode, mixed $excluded): self
    {
        $mode = $mode instanceof DocumentMode ? $mode : DocumentMode::tryFrom((string) $mode);
        $ids = [];

        foreach (is_array($excluded) ? $excluded : [] as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[(int) $id] = true;
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return new self($mode ?? DocumentMode::Complete, $ids);
    }

    public function excludes(int $slideId): bool
    {
        return in_array($slideId, $this->excludedSlideIds, true);
    }

    /**
     * Whether a slide of the composition enters the document: the mandatory ones always (cover,
     * recap); in "Solo riepilogo" only the slides of the summary (the closing), never the others;
     * otherwise every slide not excluded.
     */
    public function includes(int $slideId, bool $mandatory, bool $inSummary): bool
    {
        if ($mandatory) {
            return true;
        }

        if ($this->mode === DocumentMode::Summary && ! $inSummary) {
            return false;
        }

        return ! $this->excludes($slideId);
    }

    /**
     * The two values for the columns.
     *
     * @return array{document_mode: string, excluded_slide_ids: list<int>}
     */
    public function columns(): array
    {
        return ['document_mode' => $this->mode->value, 'excluded_slide_ids' => $this->excludedSlideIds];
    }

    public function equals(self $other): bool
    {
        return $this->mode === $other->mode && $this->excludedSlideIds === $other->excludedSlideIds;
    }
}
