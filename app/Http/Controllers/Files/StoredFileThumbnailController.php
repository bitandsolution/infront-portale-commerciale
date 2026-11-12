<?php

namespace App\Http\Controllers\Files;

use App\Domain\Files\Services\FileDerivatives;
use App\Enums\FilePurpose;
use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thumbnail of a slide image (D-420; DM-05): who may see the original (StoredFilePolicy, checked by
 * the route) gets its JPEG of at most 480×270, made at the first request and then reused
 * (FileDerivatives), served like every stored file. Only slide images have a thumbnail.
 */
final class StoredFileThumbnailController extends Controller
{
    public function __invoke(Request $request, StoredFile $storedFile, FileDerivatives $derivatives, StoredFileDownloadController $download): Response
    {
        if ($storedFile->purpose !== FilePurpose::SlideImage) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $download($request, $derivatives->thumbnail($storedFile));
    }
}
