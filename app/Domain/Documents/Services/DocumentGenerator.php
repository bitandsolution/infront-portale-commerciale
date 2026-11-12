<?php

namespace App\Domain\Documents\Services;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Exceptions\DocumentNotComposable;
use App\Domain\Files\Services\FileStorage;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\FilePurpose;
use App\Models\Document;
use App\Models\Proposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generation of a requested document, run by the job GenerateDocument (M09 §6, §9; D-125, D-204,
 * D-416; DM-04). Idempotent: a document already ready or replaced is left as it is.
 *
 * 1. status "generating", one more attempt;
 * 2. composition (DocumentBuilder): a missing mandatory slide fails the document at once, with the
 *    reason, without retrying (D-416);
 * 3. PDF (RendersPdf: Chromium, qpdf and the checks) in a temporary folder;
 * 4. in one transaction: the PDF in stored_files and the row "ready" with pages, size, page model and
 *    KPI snapshot, unless the document was replaced meanwhile.
 *
 * Any other error is kept as the last error and thrown again, so the queue retries; after the last
 * attempt the job marks the document "failed" (fail()).
 */
final class DocumentGenerator
{
    public function __construct(
        private readonly DocumentBuilder $builder,
        private readonly RendersPdf $renderer,
        private readonly FileStorage $storage,
        private readonly AuditLog $audit,
    ) {}

    public function generate(int $documentId): void
    {
        $document = Document::query()->with('group')->find($documentId);

        if ($document === null || ! $document->isCurrent() || $document->status === DocumentStatus::Ready) {
            return;
        }

        $document->forceFill(['status' => DocumentStatus::Generating, 'attempts' => $document->attempts + 1])->save();

        try {
            $built = $this->builder->build($document->group);
        } catch (DocumentNotComposable $exception) {
            $this->fail($document->id, $exception->getMessage());

            return;
        }

        $folder = storage_path('app/document-jobs/'.strtolower((string) Str::ulid()));
        File::ensureDirectoryExists($folder);

        try {
            $report = $this->renderer->render($built->pages, $built->metadata, $folder.'/document.pdf');

            DB::transaction(function () use ($document, $built, $report): void {
                $locked = Document::query()->whereKey($document->id)->lockForUpdate()->first();

                if ($locked === null || ! $locked->isCurrent()) {
                    return;
                }

                $file = $this->storage->storeContents(
                    (string) file_get_contents($report->path),
                    FilePurpose::GeneratedPdf,
                    $locked->generatedBy,
                    $built->fileName,
                );

                $locked->forceFill([
                    'status' => DocumentStatus::Ready,
                    'document_type' => $built->type,
                    'proposal_id' => $built->firstProposal->id,
                    'proposal_version_id' => $built->firstVersion->id,
                    'file_id' => $file->id,
                    'page_count' => $report->pages,
                    'size_bytes' => $report->bytes,
                    'page_model' => [...$built->pageModel, 'pdf_warnings' => $report->warnings],
                    'kpi_snapshot' => $built->kpiSnapshot,
                    'generated_at' => now(),
                    'last_error' => null,
                ])->save();

                $this->audit->record(
                    AuditAction::DocumentGenerated,
                    null,
                    AuditSubject::document($locked, $built->firstProposal),
                    ['status' => DocumentStatus::Generating->value],
                    [
                        'status' => DocumentStatus::Ready->value,
                        'file_id' => $file->id,
                        'pages' => $report->pages,
                        'size_bytes' => $report->bytes,
                    ],
                );
            });
        } catch (Throwable $exception) {
            $document->forceFill(['last_error' => self::message($exception->getMessage())])->save();

            throw $exception;
        } finally {
            File::deleteDirectory($folder);
        }
    }

    /**
     * The document cannot be generated: "failed" with the reason, for the detail of the group.
     */
    public function fail(int $documentId, string $reason): void
    {
        DB::transaction(function () use ($documentId, $reason): void {
            $document = Document::query()->whereKey($documentId)->lockForUpdate()->first();

            if ($document === null || $document->status === DocumentStatus::Ready) {
                return;
            }

            $before = $document->status;
            $document->forceFill(['status' => DocumentStatus::Failed, 'last_error' => self::message($reason)])->save();

            $this->audit->record(
                AuditAction::DocumentFailed,
                null,
                AuditSubject::document($document, Proposal::query()->findOrFail($document->proposal_id)),
                ['status' => $before->value],
                ['status' => DocumentStatus::Failed->value],
                self::message($reason),
            );
        });
    }

    private static function message(string $message): string
    {
        return mb_substr($message, 0, 1000);
    }
}
