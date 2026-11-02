<?php

namespace App\Support\Catalog;

use App\Enums\CatalogValueKind;
use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Territory;
use App\Models\VisibilityType;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Data of the page "Catalogo › Valori" (D-331) for the frontend: the values of the system data
 * shown read-only (territories, visibility types, pools) and the values the admin changes (LED
 * formats, partnership tiers, hospitality variants, benefit categories), in their order.
 */
final class CatalogPresenter
{
    /**
     * @return array{territories: list<array{code: string, name: string, isActive: bool}>, visibilityTypes: list<array{code: string, name: string, isActive: bool}>, pools: list<array{code: string, name: string, visibilityType: string, isActive: bool}>, formats: list<array<string, mixed>>, tiers: list<array<string, mixed>>, variants: list<array<string, mixed>>, categories: list<array<string, mixed>>}
     */
    public static function values(): array
    {
        return [
            'territories' => Territory::query()->orderBy('id')->get()
                ->map(fn (Territory $territory): array => ['code' => $territory->code, 'name' => $territory->name, 'isActive' => $territory->is_active])
                ->values()->all(),
            'visibilityTypes' => VisibilityType::query()->orderBy('id')->get()
                ->map(fn (VisibilityType $type): array => ['code' => $type->code, 'name' => $type->name, 'isActive' => $type->is_active])
                ->values()->all(),
            'pools' => self::ordered(InventoryPool::query()->with('visibilityType'))->get()
                ->map(fn (InventoryPool $pool): array => [
                    'code' => $pool->code,
                    'name' => $pool->name,
                    'visibilityType' => $pool->visibilityType->name,
                    'isActive' => $pool->is_active,
                ])
                ->values()->all(),
            'formats' => self::ordered(LedFormat::query()->with('pools'))->get()
                ->map(fn (LedFormat $format): array => self::valueRow($format))
                ->values()->all(),
            'tiers' => self::ordered(PartnershipTier::query())->get()
                ->map(fn (PartnershipTier $tier): array => self::valueRow($tier))
                ->values()->all(),
            'variants' => self::ordered(HospitalityVariant::query())->get()
                ->map(fn (HospitalityVariant $variant): array => self::valueRow($variant))
                ->values()->all(),
            'categories' => self::ordered(BenefitCategory::query())->get()
                ->map(fn (BenefitCategory $category): array => self::valueRow($category))
                ->values()->all(),
        ];
    }

    /**
     * A value the admin changes. Description and active are null for the kinds that do not have
     * them; notes are the fixed facts from the system data (pools consumed, free package).
     *
     * @return array{id: int, kind: string, code: string, name: string, description: string|null, isActive: bool|null, sortOrder: int, notes: list<string>, url: string}
     */
    public static function valueRow(LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value): array
    {
        $kind = CatalogValueKind::of($value);

        return [
            'id' => $value->id,
            'kind' => $kind->value,
            'code' => $value->code,
            'name' => $value->name,
            'description' => $value instanceof LedFormat ? $value->description : null,
            'isActive' => $value instanceof BenefitCategory ? null : $value->is_active,
            'sortOrder' => $value->sort_order,
            'notes' => self::notes($value),
            'url' => route('catalog.values.'.$kind->routeSegment().'.edit', $value),
        ];
    }

    /**
     * @return list<string>
     */
    private static function notes(LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value): array
    {
        if ($value instanceof LedFormat) {
            $pools = $value->pools->sortBy('sort_order')->pluck('name')->implode(' + ');

            return [Text::get('catalog.notes.pools', ['pools' => $pools === '' ? '—' : $pools])];
        }

        if ($value instanceof PartnershipTier && $value->is_free_package) {
            return [Text::get('catalog.notes.free_package')];
        }

        return [];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
