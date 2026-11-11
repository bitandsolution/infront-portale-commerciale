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
 * Change of a slide of the library (M08 F-01; D-406): every field but the season; deactivation
 * instead of deletion (C4). A new image is a new file (C10): the documents already generated keep the
 * previous one. Becoming a LED simulation drops the image (D-405). Audit slide.updated with only the
 * changed fields; nothing is written when nothing changes.
 */
final class UpdateSlide
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly FileStorage $files,
    ) {}

    public function handle(Slide $slide, SlideData $data, ?UploadedFile $image, User $actor): bool
    {
        $season = Season::query()->findOrFail($slide->season_id);

        if ($season->status === SeasonStatus::Closed) {
            throw ContentActionNotAllowed::seasonClosed($season->code);
        }

        return DB::transaction(function () use ($slide, $season, $data, $image, $actor): bool {
            $slide = Slide::query()->with('conditions')->lockForUpdate()->findOrFail($slide->id);
            $before = ContentAuditValues::slide($slide, $slide->conditionValues());

            $imageId = $slide->image_file_id;

            if ($data->layout?->needsImage() === false) {
                $imageId = null;
            } elseif ($image !== null) {
                $imageId = $this->files->storeUpload($image, FilePurpose::SlideImage, $actor)->id;
            }

            $slide->fill([
                'title' => $data->title,
                'scope' => $data->scope,
                'club_id' => $data->clubId,
                'document_type' => $data->documentType,
                'slide_section_id' => $data->sectionId,
                'sort_order' => $data->sortOrder,
                'kind' => $data->kind(),
                'image_file_id' => $imageId,
                'layout_code' => $data->layout,
                'is_active' => $data->isActive,
            ]);

            $conditions = SlideConditions::of($data);

            [$old, $new] = AuditLog::changes($before, ContentAuditValues::slide($slide, $conditions));

            if ($new === [] && $old === []) {
                return false;
            }

            $slide->save();

            if (array_key_exists('conditions', $new)) {
                SlideConditions::write($slide, $data);
            }

            $this->audit->record(AuditAction::SlideUpdated, $actor, AuditSubject::slide($slide, $season), $old, $new);

            return true;
        });
    }
}
