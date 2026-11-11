<?php

namespace Database\Factories;

use App\Domain\Files\Services\FileStorage;
use App\Enums\DocumentLayout;
use App\Enums\FilePurpose;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideKind;
use App\Enums\SlideScope;
use App\Models\Club;
use App\Models\Season;
use App\Models\Slide;
use App\Models\SlideSection;
use App\Support\Files\SampleFiles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * Fictitious static Infront slides for both documents, with a PNG 1920×1080 stored through
 * FileStorage (the tests need Storage::fake(StoredFile::DISK)) and the sections of the system data
 * (ContentSystemDataSeeder must have run). forClub(), layout() and when() set scope, layout and
 * conditions.
 *
 * @extends Factory<Slide>
 */
class SlideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Slide '.fake()->unique()->numberBetween(1, 99999),
            'scope' => SlideScope::Infront,
            'club_id' => null,
            'season_id' => fn (): int => Season::factory()->create()->id,
            'document_type' => SlideDocumentType::Both,
            'slide_section_id' => fn (): int => SlideSection::query()->where('code', 'INTRO')->valueOrFail('id'),
            'sort_order' => 10,
            'kind' => SlideKind::Static,
            'image_file_id' => fn (): int => DB::transaction(fn (): int => app(FileStorage::class)
                ->storeContents(SampleFiles::png(1920, 1080), FilePurpose::SlideImage, null, 'slide-prova.png')->id),
            'layout_code' => null,
            'language' => Slide::LANGUAGE,
            'is_active' => true,
        ];
    }

    public function forSeason(Season $season): static
    {
        return $this->state(['season_id' => $season->id]);
    }

    public function forClub(Club $club): static
    {
        return $this->state(['scope' => SlideScope::Club, 'club_id' => $club->id]);
    }

    public function document(SlideDocumentType $type): static
    {
        return $this->state(['document_type' => $type]);
    }

    public function section(string $code, int $sortOrder = 10): static
    {
        return $this->state(fn (): array => [
            'slide_section_id' => SlideSection::query()->where('code', $code)->valueOrFail('id'),
            'sort_order' => $sortOrder,
        ]);
    }

    public function layout(DocumentLayout $layout): static
    {
        return $this->state(fn (): array => ['kind' => SlideKind::Dynamic, 'layout_code' => $layout]
            + ($layout->needsImage() ? [] : ['image_file_id' => null]));
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Inclusion conditions, e.g. when(SlideConditionType::PartnershipTier, 'TOP', 'PREMIUM').
     */
    public function when(SlideConditionType $type, string ...$values): static
    {
        return $this->afterCreating(function (Slide $slide) use ($type, $values): void {
            foreach ($values as $value) {
                $slide->conditions()->create(['condition_type' => $type, 'value' => $value]);
            }
        });
    }
}
