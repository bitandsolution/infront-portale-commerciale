<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Files\Services\FileStorage;
use App\Enums\AuditAction;
use App\Enums\FilePurpose;
use App\Models\Club;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * New logo of a club (M02 F-05; D-218, D-230): admin only. The file is checked from its content and
 * re-encoded by FileStorage inside the transaction (D-288, D-290); the previous file is never
 * overwritten (C10), so documents already generated keep their logo. Audit club.logo_changed with
 * the file before and after (M10 §4.1: the upload is part of the event of the entity).
 */
final class ChangeClubLogo
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly FileStorage $files,
    ) {}

    public function handle(Club $club, UploadedFile $logo, User $actor): Club
    {
        return DB::transaction(function () use ($club, $logo, $actor): Club {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $old = $club->logo_file_id;

            $file = $this->files->storeUpload($logo, FilePurpose::ClubLogo, $actor);
            $club->update(['logo_file_id' => $file->id]);

            $this->audit->record(
                AuditAction::ClubLogoChanged,
                $actor,
                AuditSubject::club($club),
                ['logo_file_id' => $old],
                ['logo_file_id' => $file->id],
            );

            return $club;
        });
    }
}
