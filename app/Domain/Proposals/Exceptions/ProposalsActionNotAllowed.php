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
    public static function variantsShareHeader(): self
    {
        return new self(Text::get('proposals.errors.variants_share_header'));
    }

        public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
