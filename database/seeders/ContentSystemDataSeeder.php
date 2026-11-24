<?php

namespace Database\Seeders;

use App\Enums\KpiGroup;
use App\Enums\KpiUnit;
use App\Models\KpiDefinition;
use App\Models\SlideSection;
use Illuminate\Database\Seeder;

/**
 * System data of the contents (progettazione-tecnica §5.6.1 A1; M08 §4.1, §5; D-406, D-490): the fixed
 * sections of the slides, in the order of the documents, and the 13 KPI of D-108. Called by
 * SystemDataSeeder.
 *
 * Idempotent like SystemDataSeeder (D-295): sections and KPI are found by code and only the missing
 * ones are created; names and flags already in the database are left unchanged.
 */
class ContentSystemDataSeeder extends Seeder
{
    /**
     * code, name, sort order.
     *
     * @var list<array{string, string, int}>
     */
    public const SECTIONS = [
        ['INTRO', 'Introduzione', 10],
        ['CLUB', 'Club', 20],
        ['PARTNERSHIP', 'Partnership', 30],
        ['BENEFIT', 'Benefit', 40],
        ['BRAND_EXPOSURE', 'Brand exposure', 50],
        ['RECAP', 'Recap', 60],
        [SlideSection::CLOSING, 'Chiusura', 70],
    ];

    /**
     * The KPI of D-108 (D-490): code, name, group, unit, summed in the network document, sort order.
     * Network sums: fanbase ("tifosi"), home audience ("audience live"), annual attendance
     * ("spettatori stadio") and the five social networks (D-108; to be confirmed with Infront before
     * F7).
     *
     * @var list<array{string, string, KpiGroup, KpiUnit, bool, int}>
     */
    public const KPIS = [
        ['FANBASE_ITALY', 'Fanbase Italia', KpiGroup::Club, KpiUnit::People, true, 10],
        ['AVERAGE_MATCH_FANS', 'Tifosi medi a partita', KpiGroup::Club, KpiUnit::People, false, 20],
        ['ANNUAL_ATTENDANCE', 'Presenze annuali', KpiGroup::Club, KpiUnit::People, true, 30],
        ['HOME_AUDIENCE', 'Audience casalinga cumulata', KpiGroup::Club, KpiUnit::People, true, 40],
        ['DEDICATED_BROADCASTS', 'Trasmissioni dedicate', KpiGroup::Club, KpiUnit::Broadcasts, false, 50],
        ['SEASON_TICKETS', 'Abbonati', KpiGroup::Club, KpiUnit::People, false, 60],
        ['FACEBOOK_FOLLOWERS', 'Follower Facebook', KpiGroup::Digital, KpiUnit::Followers, true, 10],
        ['INSTAGRAM_FOLLOWERS', 'Follower Instagram', KpiGroup::Digital, KpiUnit::Followers, true, 20],
        ['X_FOLLOWERS', 'Follower X', KpiGroup::Digital, KpiUnit::Followers, true, 30],
        ['YOUTUBE_FOLLOWERS', 'Follower YouTube', KpiGroup::Digital, KpiUnit::Followers, true, 40],
        ['TIKTOK_FOLLOWERS', 'Follower TikTok', KpiGroup::Digital, KpiUnit::Followers, true, 50],
        ['DIGITAL_USERS', 'Utenti digitali', KpiGroup::Digital, KpiUnit::Users, false, 60],
        ['DEM_CONTACTS', 'Contatti DEM', KpiGroup::Digital, KpiUnit::Contacts, false, 70],
    ];

    public function run(): void
    {
        $this->sections();
        $this->kpis();
    }

    private function sections(): void
    {
        $created = 0;

        foreach (self::SECTIONS as [$code, $name, $sortOrder]) {
            $section = SlideSection::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'sort_order' => $sortOrder,
            ]);

            if ($section->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command?->line(sprintf(
            'Slide sections: %d created, %d already present (left unchanged).',
            $created,
            count(self::SECTIONS) - $created,
        ));
    }

    private function kpis(): void
    {
        $created = 0;

        foreach (self::KPIS as [$code, $name, $group, $unit, $networkAggregate, $sortOrder]) {
            $definition = KpiDefinition::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'group' => $group,
                'unit' => $unit,
                'network_aggregate' => $networkAggregate,
                'sort_order' => $sortOrder,
            ]);

            if ($definition->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command?->line(sprintf(
            'KPI definitions: %d created, %d already present (left unchanged).',
            $created,
            count(self::KPIS) - $created,
        ));
    }
}
