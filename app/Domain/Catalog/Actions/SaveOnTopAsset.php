<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Catalog\Exceptions\CatalogActionNotAllowed;
use App\Domain\Catalog\Services\PriceListGrid;
use App\Enums\AuditAction;
use App\Models\Club;
use App\Models\OnTopAsset;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * New or changed on-top text of a price list (M03 F-10; D-049, D-335): branded content, match
 * sponsor, activations, only descriptive (no price). For a club of the grid, or general.
 *
 * - A new text goes after the others of the same club (or the general ones).
 * - Refused in a closed season. Audit on_top_asset.created / on_top_asset.updated with club, title
 *   and text (only the changed fields on a change); nothing written when nothing changes.
 */
final class SaveOnTopAsset
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly PriceListGrid $grid,
    ) {}

    public function create(PriceList $priceList, ?Club $club, string $title, string $body, User $actor): OnTopAsset
    {
        return DB::transaction(function () use ($priceList, $club, $title, $body, $actor): OnTopAsset {
            $priceList = $this->lockWritable($priceList);

            if ($club !== null && ! $this->grid->hasClub($priceList->season, $club)) {
                throw CatalogActionNotAllowed::clubNotInGrid($club->name, $priceList->season->code);
            }

            $last = OnTopAsset::query()
                ->where('price_list_id', $priceList->id)
                ->where('club_id', $club?->id)
                ->max('sort_order');

            $asset = OnTopAsset::query()->create([
                'price_list_id' => $priceList->id,
                'club_id' => $club?->id,
                'title' => $title,
                'body' => $body,
                'sort_order' => is_numeric($last) ? (int) $last + 1 : 0,
            ]);

            $this->audit->record(
                AuditAction::OnTopAssetCreated,
                $actor,
                AuditSubject::onTopAsset($priceList, $asset, $club),
                [],
                ['club' => $club?->code, 'title' => $title, 'body' => $body],
            );

            return $asset;
        });
    }

    public function update(OnTopAsset $asset, string $title, string $body, User $actor): bool
    {
        return DB::transaction(function () use ($asset, $title, $body, $actor): bool {
            $priceList = $this->lockWritable($asset->priceList);
            $asset = OnTopAsset::query()->with('club')->lockForUpdate()->findOrFail($asset->id);

            [$old, $new] = AuditLog::changes(
                ['title' => $asset->title, 'body' => $asset->body],
                ['title' => $title, 'body' => $body],
            );

            if ($new === []) {
                return false;
            }

            $asset->title = $title;
            $asset->body = $body;
            $asset->save();

            $this->audit->record(AuditAction::OnTopAssetUpdated, $actor, AuditSubject::onTopAsset($priceList, $asset, $asset->club), $old, $new);

            return true;
        });
    }

    public function remove(OnTopAsset $asset, User $actor): void
    {
        DB::transaction(function () use ($asset, $actor): void {
            $priceList = $this->lockWritable($asset->priceList);
            $asset = OnTopAsset::query()->with('club')->lockForUpdate()->findOrFail($asset->id);
            $subject = AuditSubject::onTopAsset($priceList, $asset, $asset->club);
            $values = ['club' => $asset->club?->code, 'title' => $asset->title, 'body' => $asset->body];

            $asset->delete();

            $this->audit->record(AuditAction::OnTopAssetRemoved, $actor, $subject, $values, []);
        });
    }

    private function lockWritable(PriceList $priceList): PriceList
    {
        $priceList = PriceList::query()->with('season')->lockForUpdate()->findOrFail($priceList->id);

        if ($priceList->season->isReadOnly()) {
            throw CatalogActionNotAllowed::seasonClosed($priceList->season->code);
        }

        return $priceList;
    }
}
