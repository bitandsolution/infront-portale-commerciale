<?php

namespace Database\Seeders;

use App\Models\SlideSection;
use Illuminate\Database\Seeder;

/**
 * System data of the contents (progettazione-tecnica §5.6.1 A1; M08 §4.1; D-406): the fixed sections
 * of the slides, in the order of the documents. Called by SystemDataSeeder.
 *
 * Idempotent like SystemDataSeeder (D-295): sections are found by code and only the missing ones are
 * created.
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

    public function run(): void
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
}
