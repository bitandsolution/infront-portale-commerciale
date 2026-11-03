<?php

namespace Database\Seeders;

use App\Models\BenefitCategory;
use App\Models\HospitalityVariant;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Territory;
use App\Models\VisibilityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * System data of the catalogue (progettazione-tecnica §5.6.1 A1; D-331, D-332): territories,
 * visibility types, pools, LED formats with the pools they consume, partnership tiers, hospitality
 * variants and benefit categories. Called by SystemDataSeeder.
 *
 * The codes and the pools consumed by each format come only from here (D-331): the portal never
 * creates or deletes a value. Idempotent like SystemDataSeeder (D-295): rows are found by code and
 * only the missing ones are created; a value renamed, reordered or deactivated by the admin is
 * never changed back. A missing pool of a format is added again; a pool is never removed.
 *
 * Not created (D-332): the tier DIGITAL and the historical formats (corner bassi, sotto tribuna),
 * which served only the migration (D-193, D-227).
 */
class CatalogSystemDataSeeder extends Seeder
{
    /**
     * code, name, active (D-030).
     *
     * @var list<array{string, string, bool}>
     */
    public const TERRITORIES = [
        ['ITALIA', 'Italia', true],
        ['ESTERO', 'Estero', false],
    ];

    /**
     * code, name, active (D-021).
     *
     * @var list<array{string, string, bool}>
     */
    public const VISIBILITY_TYPES = [
        ['CLASSICA', 'Classica', true],
        ['VIRTUAL', 'Virtual', false],
    ];

    /**
     * code, name, visibility type, active (D-046), sort order.
     *
     * @var list<array{string, string, string, bool, int}>
     */
    public const POOLS = [
        ['FILA_1', '1ª fila', 'CLASSICA', true, 10],
        ['FILA_2', '2ª fila', 'CLASSICA', true, 20],
        ['CORNER_BASSI', 'Corner bassi', 'CLASSICA', false, 30],
        ['SOTTO_TRIBUNA', 'Sotto tribuna', 'CLASSICA', false, 40],
    ];

    /**
     * code, name, visibility type, pools consumed (D-051), sort order.
     *
     * @var list<array{string, string, string, list<string>, int}>
     */
    public const LED_FORMATS = [
        ['JUMBO', 'Jumbo', 'CLASSICA', ['FILA_1', 'FILA_2'], 10],
        ['DOMINATION', 'Domination', 'CLASSICA', ['FILA_1', 'FILA_2'], 20],
        ['FILA_1', '1ª fila', 'CLASSICA', ['FILA_1'], 30],
        ['FILA_2', '2ª fila', 'CLASSICA', ['FILA_2'], 40],
    ];

    /**
     * code, name, free package (D-052), sort order (D-050).
     *
     * @var list<array{string, string, bool, int}>
     */
    public const PARTNERSHIP_TIERS = [
        ['LIBERA', 'Libera', true, 10],
        ['TOP', 'Top', false, 20],
        ['PREMIUM', 'Premium', false, 30],
        ['OFFICIAL', 'Official', false, 40],
        ['OFFICIAL_2', 'Official 2.0', false, 50],
    ];

    /**
     * code, name, sort order (D-047).
     *
     * @var list<array{string, string, int}>
     */
    public const HOSPITALITY_VARIANTS = [
        ['PREMIUM', 'Premium', 10],
        ['BASE', 'Base', 20],
    ];

    /**
     * code, name, sort order (spec-M03 §4).
     *
     * @var list<array{string, string, int}>
     */
    public const BENEFIT_CATEGORIES = [
        ['IP_RIGHTS', 'IP rights', 10],
        ['BRAND_EXPOSURE', 'Brand exposure stadio e centro sportivo', 20],
        ['DIGITAL_SOCIAL', 'Digital & social', 30],
        ['TICKETING_HOSPITALITY', 'Ticketing & hospitality', 40],
        ['EXCLUSIVE_EXPERIENCES', 'Esperienze esclusive', 50],
        ['MERCHANDISING', 'Merchandising', 60],
        ['SERVICES', 'Servizi', 70],
        ['NOTES', 'Note', 80],
    ];

    /**
     * Rows created by this run, by table (for the line of the command).
     *
     * @var array<string, int>
     */
    private array $created = [];

    public function run(): void
    {
        foreach (self::TERRITORIES as [$code, $name, $active]) {
            $this->ensure(Territory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => $active]));
        }

        /** @var array<string, VisibilityType> $types */
        $types = [];
        foreach (self::VISIBILITY_TYPES as [$code, $name, $active]) {
            $types[$code] = $this->ensure(VisibilityType::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => $active]));
        }

        /** @var array<string, InventoryPool> $pools */
        $pools = [];
        foreach (self::POOLS as [$code, $name, $type, $active, $sortOrder]) {
            $pools[$code] = $this->ensure(InventoryPool::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'visibility_type_id' => $types[$type]->id,
                'is_active' => $active,
                'sort_order' => $sortOrder,
            ]));
        }

        $poolLinks = 0;
        foreach (self::LED_FORMATS as [$code, $name, $type, $consumed, $sortOrder]) {
            $format = $this->ensure(LedFormat::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'description' => null,
                'visibility_type_id' => $types[$type]->id,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]));

            $missing = array_diff(
                array_map(fn (string $pool): int => $pools[$pool]->id, $consumed),
                $format->pools()->pluck('inventory_pools.id')->all(),
            );
            $format->pools()->attach($missing);
            $poolLinks += count($missing);
        }

        foreach (self::PARTNERSHIP_TIERS as [$code, $name, $free, $sortOrder]) {
            $this->ensure(PartnershipTier::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'is_free_package' => $free,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]));
        }

        foreach (self::HOSPITALITY_VARIANTS as [$code, $name, $sortOrder]) {
            $this->ensure(HospitalityVariant::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true, 'sort_order' => $sortOrder]));
        }

        foreach (self::BENEFIT_CATEGORIES as [$code, $name, $sortOrder]) {
            $this->ensure(BenefitCategory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => $sortOrder]));
        }

        $this->command?->line(sprintf(
            'Catalogue: %d values created, %d already present (left unchanged); %d pools of formats added.',
            array_sum($this->created),
            $this->expected() - array_sum($this->created),
            $poolLinks,
        ));
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function ensure(Model $model): Model
    {
        if ($model->wasRecentlyCreated) {
            $this->created[$model->getTable()] = ($this->created[$model->getTable()] ?? 0) + 1;
        }

        return $model;
    }

    private function expected(): int
    {
        return count(self::TERRITORIES) + count(self::VISIBILITY_TYPES) + count(self::POOLS)
            + count(self::LED_FORMATS) + count(self::PARTNERSHIP_TIERS)
            + count(self::HOSPITALITY_VARIANTS) + count(self::BENEFIT_CATEGORIES);
    }
}
