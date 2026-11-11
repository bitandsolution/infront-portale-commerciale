<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Content\Data\ContentAuditValues;
use App\Domain\Content\Data\SlideData;
use App\Domain\Content\Exceptions\ContentActionNotAllowed;
use App\Domain\Files\Services\FileStorage;
use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * New slide of the library (M08 F-01…F-03; D-115, D-116, D-402…D-406): admin and manager, in the
 * season of the top bar, never in a closed season (D-317). The PNG 1920×1080 is checked and
 * re-encoded by FileStorage inside the transaction (D-288); the LED simulation has no image (D-405).
 * The conditions are one row per value (D-202). Audit slide.created with every field.
 */
final class CreateSlide
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly FileStorage $files,
    ) {}

    public function handle(Season $season, SlideData $data, ?UploadedFile $image, User $actor): Slide
    {
        if ($season->status === SeasonStatus::Closed) {
            throw ContentActionNotAllowed::seasonClosed($season->code);
        }

        return DB::transaction(function () use ($season, $data, $image, $actor): Slide {
            $imageId = null;

            if ($data->layout?->needsImage() !== false && $image !== null) {
                $imageId = $this->files->storeUpload($image, FilePurpose::SlideImage, $actor)->id;
            }

            $slide = Slide::query()->create([
                'title' => $data->title,
                'scope' => $data->scope,
                'club_id' => $data->clubId,
                'season_id' => $season->id,
                'document_type' => $data->documentType,
                'slide_section_id' => $data->sectionId,
                'sort_order' => $data->sortOrder,
                'kind' => $data->kind(),
                'image_file_id' => $imageId,
                'layout_code' => $data->layout,
                'language' => Slide::LANGUAGE,
                'is_active' => $data->isActive,
                'created_by' => $actor->id,
            ]);

            $conditions = SlideConditions::write($slide, $data);

            $this->audit->record(
                AuditAction::SlideCreated,
                $actor,
                AuditSubject::slide($slide, $season),
                [],
                ContentAuditValues::slide($slide, $conditions),
            );

            return $slide;
        });
    }
}
