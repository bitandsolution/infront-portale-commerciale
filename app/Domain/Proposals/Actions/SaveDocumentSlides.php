<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\DocumentSelection;
use App\Domain\Content\Services\DocumentComposer;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\AuditAction;
use App\Enums\DocumentMode;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroup;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Slides chosen for the document of a group (D-397, D-418, D-419; DM-05): the mode ("Documento
 * completo" / "Solo riepilogo") and the excluded slides, saved on the group.
 *
 * - Who: the agent of the group while every variant is a draft or rejected; admin and manager while
 *   the group waits for approval (D-397). Otherwise ProposalsActionNotAllowed (the permission on the
 *   group is checked by ProposalGroupPolicy::editDocument).
 * - What: only slides of the current composition of the group that are not mandatory can be
 *   excluded (cover and recap never); unknown ids are ignored. Exclusions of slides that are not in
 *   the composition now (a condition no longer true) are kept, so they still hold if the slide comes
 *   back.
 * - Slides composed with blocks (DM-07): their tick is saved on each of them (is_included); without
 *   the list (null) they stay as they are.
 * - Nothing changes: nothing is written, no event. Otherwise one audit event on the first variant,
 *   with the group, the mode and the titles of the excluded slides.
 *
 * The group row is locked: sending, approval and rejection lock it first too.
 */
final class SaveDocumentSlides
{
    public function __construct(
        private readonly DocumentComposer $composer,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  list<int>  $excludedSlideIds
     * @param  list<int>|null  $excludedCustomSlideIds  null: the ticks of the composed slides do not change
     */
    public function handle(ProposalGroup $group, DocumentMode $mode, array $excludedSlideIds, User $actor, ?array $excludedCustomSlideIds = null): DocumentSelection
    {
        return DB::transaction(function () use ($group, $mode, $excludedSlideIds, $actor, $excludedCustomSlideIds): DocumentSelection {
            $locked = ProposalGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $variants = Proposal::query()->where('proposal_group_id', $locked->id)->orderBy('variant_letter')->get();

            if (! self::editableBy($actor, $locked, $variants->all())) {
                throw ProposalsActionNotAllowed::documentNotEditable();
            }

            $before = $locked->documentSelection();
            $composition = $this->composer->forGroup($locked);
            $listed = [];
            $optional = [];

            foreach ($composition->slides as $composed) {
                if ($composed->custom !== null) {
                    continue;
                }

                $listed[$composed->slide->id] = true;

                if (! $composed->mandatory) {
                    $optional[$composed->slide->id] = true;
                }
            }

            $ids = [];

            foreach ($before->excludedSlideIds as $id) {
                if (! isset($listed[$id])) {
                    $ids[$id] = true;
                }
            }

            foreach ($excludedSlideIds as $id) {
                if (isset($optional[$id])) {
                    $ids[$id] = true;
                }
            }

            $ids = array_keys($ids);
            sort($ids);
            $after = new DocumentSelection($mode, $ids);
            $customSlides = $locked->customSlides()->get();
            $customBefore = self::excludedCustom($customSlides->all());

            if ($excludedCustomSlideIds !== null) {
                foreach ($customSlides as $customSlide) {
                    $customSlide->is_included = ! in_array($customSlide->id, $excludedCustomSlideIds, true);

                    if ($customSlide->isDirty('is_included')) {
                        $customSlide->save();
                    }
                }
            }

            $customAfter = self::excludedCustom($customSlides->all());

            if ($after->equals($before) && $customAfter === $customBefore) {
                return $before;
            }

            if (! $after->equals($before)) {
                $locked->forceFill($after->columns())->save();
            }

            /** @var Proposal $first */
            $first = $variants->first();
            [$old, $new] = AuditLog::changes(
                [...self::auditValues($before, $composition->slides), 'excluded_custom_slides' => $customBefore],
                [...self::auditValues($after, $composition->slides), 'excluded_custom_slides' => $customAfter],
            );

            $this->audit->record(
                AuditAction::ProposalDocumentSlidesUpdated,
                $actor,
                AuditSubject::proposal($first),
                $old,
                ['proposal_group_id' => $locked->id, ...$new],
            );

            return $after;
        });
    }

    /**
     * D-397: the agent on drafts and rejected groups, who approves while the group waits.
     *
     * @param  array<int, Proposal>  $variants
     */
    public static function editableBy(User $actor, ProposalGroup $group, array $variants): bool
    {
        if ($variants === []) {
            return false;
        }

        if ($group->isOwnedBy($actor) && $actor->can('submit', $group)) {
            return collect($variants)->every(fn (Proposal $variant): bool => $variant->isEditable());
        }

        if ($actor->can('review', ProposalGroup::class)) {
            return collect($variants)->every(fn (Proposal $variant): bool => $variant->status === ProposalStatus::Submitted);
        }

        return false;
    }

    /**
     * Titles of the composed slides left out of the document, null when none.
     *
     * @param  array<int, ProposalCustomSlide>  $slides
     */
    private static function excludedCustom(array $slides): ?string
    {
        $titles = array_map(
            fn (ProposalCustomSlide $slide): string => $slide->title,
            array_values(array_filter($slides, fn (ProposalCustomSlide $slide): bool => ! $slide->is_included)),
        );

        return $titles !== [] ? implode(', ', $titles) : null;
    }

    /**
     * Mode and excluded slides by title (the ids of slides no longer in the composition by id).
     *
     * @param  list<ComposedSlide>  $slides
     * @return array{document_mode: string, excluded_slides: string|null}
     */
    private static function auditValues(DocumentSelection $selection, array $slides): array
    {
        $titles = [];

        foreach ($slides as $composed) {
            if ($composed->custom === null) {
                $titles[$composed->slide->id] = $composed->slide->title;
            }
        }

        $missing = array_values(array_filter($selection->excludedSlideIds, fn (int $id): bool => ! isset($titles[$id])));
        /** @var array<int, string> $known */
        $known = $missing !== [] ? Slide::query()->whereKey($missing)->pluck('title', 'id')->all() : [];
        $names = array_map(fn (int $id): string => $titles[$id] ?? $known[$id] ?? '#'.$id, $selection->excludedSlideIds);

        return [
            'document_mode' => $selection->mode->value,
            'excluded_slides' => $names !== [] ? implode(', ', $names) : null,
        ];
    }
}
