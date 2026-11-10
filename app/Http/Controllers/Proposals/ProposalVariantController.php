<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Proposals\Actions\AddVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\AddVariantRequest;
use App\Models\Proposal;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

/**
 * Variants of a proposal (D-396; slice DM, DM-01): "Aggiungi variante" from the configurator makes a
 * copy of the draft in the same group and opens it at the same step (the summary only when the copy
 * can show it: the configurator moves back to step 4 by itself, D-373).
 */
class ProposalVariantController extends Controller
{
    public function store(AddVariantRequest $request, Proposal $proposal, AddVariant $addVariant): RedirectResponse
    {
        $result = $addVariant->handle($proposal, $request->actor());
        $variant = $result->proposal;
        $step = $result->hasClubs ? $request->integer('step', 4) : min(2, $request->integer('step', 2));

        return redirect()
            ->route('proposals.edit', ['proposal' => $variant, 'passo' => max(1, $step)])
            ->with('toast', Toast::success(Text::get('proposals.toasts.variant_added', [
                'letter' => (string) $variant->variant_letter,
                'number' => (string) $variant->number,
            ])));
    }
}
