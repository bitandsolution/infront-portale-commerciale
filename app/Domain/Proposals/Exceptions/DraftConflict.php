<?php

namespace App\Domain\Proposals\Exceptions;

use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * The draft changed after the page read it (lock_version, D-165, D-216): typically the same draft
 * open in two windows or on two devices. Nothing has been written; the page tells the agent to reload
 * the draft instead of overwriting the other change.
 */
final class DraftConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(Text::get('proposals.validation.conflict'));
    }

    public function render(): RedirectResponse
    {
        return back()->withErrors(['conflict' => $this->getMessage()]);
    }
}
