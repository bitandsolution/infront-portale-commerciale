<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Proposals\Actions\SaveDocumentSlides;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\DocumentSlidesRequest;
use App\Models\ProposalGroup;
use Illuminate\Http\RedirectResponse;

/**
 * Slides chosen for the document of a group (D-397, D-418, D-419; DM-05), from the step "Documento"
 * of the configurator (agent) and from the detail of the group (admin and manager while it waits for
 * approval). Saved at every tick, without a toast: the page reloads the prop "documentSlides".
 */
class ProposalGroupDocumentController extends Controller
{
    public function update(DocumentSlidesRequest $request, ProposalGroup $group, SaveDocumentSlides $saveDocumentSlides): RedirectResponse
    {
        $saveDocumentSlides->handle($group, $request->mode(), $request->excludedSlideIds(), $request->actor(), $request->excludedCustomSlideIds());

        return back();
    }
}
