<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Actions\RequestDocument;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ProposalGroup;
use App\Models\User;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Regeneration of the document of a group by the admin (M09 F-05; D-125, D-205, D-414, D-416; DM-04):
 * after the library has been fixed, or after the choice of the client (then only the chosen variant).
 * The download goes through files.download (StoredFilePolicy → DocumentPolicy).
 */
class DocumentController extends Controller
{
    public function regenerate(Request $request, ProposalGroup $group, RequestDocument $requestDocument): RedirectResponse
    {
        Gate::authorize('regenerate', Document::class);
        Gate::authorize('view', $group);

        /** @var User $actor */
        $actor = $request->user();
        $requestDocument->handle($group, $actor, regeneration: true);

        return redirect()->route('proposals.groups.show', $group)->with('toast', Toast::success(Text::get('documents.toasts.regenerating')));
    }
}
