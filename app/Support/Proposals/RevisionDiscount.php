<?php

namespace App\Support\Proposals;

use App\Domain\Proposals\Data\DiscountInput;
use App\Models\Proposal;
use Illuminate\Http\Request;

/**
 * The discount typed in the box "Nuova versione" of the configurator (D-467; F5-04), sent as the query
 * of a partial reload ("sconto_tipo", "sconto") so that the server shows its preview: parsed like the
 * discounts of the detail of a group (DiscountForm). Without the query: no preview, the discount of the
 * current version.
 */
final class RevisionDiscount
{
    /**
     * @return array{0: DiscountInput|null, 1: string|null} preview and its error
     */
    public static function fromQuery(Request $request, Proposal $proposal): array
    {
        if (! $request->query->has('sconto_tipo')) {
            return [null, null];
        }

        $parsed = DiscountForm::parse([$proposal->id => [
            'type' => $request->query('sconto_tipo'),
            'value' => $request->query('sconto'),
        ]]);

        return [$parsed['discounts'][$proposal->id] ?? new DiscountInput, $parsed['errors'][$proposal->id] ?? null];
    }
}
