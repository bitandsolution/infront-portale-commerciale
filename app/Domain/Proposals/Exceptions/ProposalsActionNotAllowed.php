<?php

namespace App\Domain\Proposals\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the configurator refuses the operation (M06 §7, §8). Nothing has been written. In the
 * portal the user goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class ProposalsActionNotAllowed extends RuntimeException
{
    /** D-069: only an active agent makes proposals. */
    public static function notAgent(): self
    {
        return new self(Text::get('proposals.errors.not_agent'));
    }

    /** M05 F-02, D-366, D-368: only an active, not merged client of the agent is chosen. */
    public static function clientNotSelectable(): self
    {
        return new self(Text::get('proposals.errors.client_not_selectable'));
    }

    /** D-317: a closed season is read-only, no proposal is made on it. */
    public static function seasonNotSelectable(): self
    {
        return new self(Text::get('proposals.errors.season_not_selectable'));
    }

    /** M07 RB-08, D-374: only a draft is changed or deleted in the configurator. */
    public static function notDraft(int $number): self
    {
        return new self(Text::get('proposals.errors.not_draft', ['number' => (string) $number]));
    }

    /** M06 §5 (F4-05): the copy needs a club of the draft and at least another club to copy it on. */
    public static function nothingToCopy(): self
    {
        return new self(Text::get('proposals.errors.nothing_to_copy'));
    }

    /** D-091, RB-14 (F4-05): only an own proposal with its configuration is duplicated. */
    public static function notDuplicable(int $number): self
    {
        return new self(Text::get('proposals.errors.not_duplicable', ['number' => (string) $number]));
    }

    /** D-396 (DM-01): at most the variants of the settings in a group. */
    public static function tooManyVariants(int $max): self
    {
        return new self(Text::get('proposals.errors.too_many_variants', ['max' => (string) $max]));
    }

    /** D-396 (DM-01): the variants of a group share client and season. */
    /**
     * DM-03a: the group goes for approval only when every variant can (M06 RB-11, D-373).
     *
     * @param  list<string>  $letters
     */
    public static function groupNotReady(array $letters): self
    {
        return new self(Text::get('proposals.errors.group_not_ready', ['variants' => implode(', ', $letters)]));
    }

    public static function groupNotSubmittable(): self
    {
        return new self(Text::get('proposals.errors.group_not_submittable'));
    }

    public static function groupNotSubmitted(): self
    {
        return new self(Text::get('proposals.errors.group_not_submitted'));
    }

    /**
     * D-397, D-419 (DM-05): the agent chooses the slides while the group is a draft or rejected,
     * admin and manager while it waits for approval.
     */
    public static function documentNotEditable(): self
    {
        return new self(Text::get('proposals.errors.document_not_editable'));
    }

    /**
     * DM-03b (D-216, D-409): a variant is not valid any more or the group asks more than the residual.
     *
     * @param  list<string>  $problems
     */
    public static function approvalBlocked(array $problems): self
    {
        return new self(Text::get('proposals.errors.approval_blocked', ['problems' => implode('; ', $problems)]));
    }

    /** M07 §8 (D-097, D-103): percentage 0…100, amount 0…list total. */
    public static function discountOutOfRange(): self
    {
        return new self(Text::get('proposals.errors.discount_out_of_range'));
    }

    /** D-410: the choice of the client only on an approved group of two or more variants, once. */
    public static function groupNotApproved(): self
    {
        return new self(Text::get('proposals.errors.group_not_approved'));
    }

    public static function variantNotInGroup(): self
    {
        return new self(Text::get('proposals.errors.variant_not_in_group'));
    }

    public static function variantsShareHeader(): self
    {
        return new self(Text::get('proposals.errors.variants_share_header'));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
