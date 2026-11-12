<?php

namespace App\Domain\Documents\Exceptions;

use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A rule of the documents refuses the operation (M09). Nothing has been written. In the portal the
 * user goes back to the page with the reason in a toast (ux-architettura §4.5).
 */
final class DocumentsActionNotAllowed extends RuntimeException
{
    /** M09 FL-01, D-090: a document only for an approved group. */
    public static function groupNotApproved(): self
    {
        return new self(Text::get('documents.errors.group_not_approved'));
    }

    public function render(): RedirectResponse
    {
        return back()->with('toast', Toast::error($this->getMessage()));
    }
}
