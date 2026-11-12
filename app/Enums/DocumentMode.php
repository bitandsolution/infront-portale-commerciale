<?php

namespace App\Enums;

use App\Support\Text;

/**
 * What the document of a group contains (D-397, D-418; DM-05), stored in
 * proposal_groups.document_mode and, frozen, in proposal_versions.document_mode (C8):
 * - complete: every slide of the composition but the excluded ones; slides added later to the
 *   library, or due to a change of the proposal, enter by themselves;
 * - summary: only cover, recap and closing (the closing can still be excluded); the LED simulation
 *   and every other slide stay out.
 */
enum DocumentMode: string
{
    case Complete = 'complete';
    case Summary = 'summary';

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        return Text::get('content.document_modes.'.$this->value);
    }
}
