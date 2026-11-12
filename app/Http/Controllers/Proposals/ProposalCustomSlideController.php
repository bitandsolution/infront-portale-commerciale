<?php

namespace App\Http\Controllers\Proposals;

use App\Domain\Content\Data\CustomSlide;
use App\Domain\Content\Services\DocumentComposer;
use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Layouts\CustomBlocksLayout;
use App\Domain\Documents\Services\CustomSlideImages;
use App\Domain\Proposals\Actions\AttachCustomSlideImage;
use App\Domain\Proposals\Actions\CreateCustomSlide;
use App\Domain\Proposals\Actions\DeleteCustomSlide;
use App\Domain\Proposals\Actions\SaveCustomSlide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\CustomSlideImageRequest;
use App\Http\Requests\Proposals\CustomSlideRequest;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroup;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Slides composed with blocks of a group (D-399, D-428…D-432; DM-07), from the step "Documento" of
 * the configurator (agent) and from the detail of the group (admin and manager while it waits): add,
 * save (shortly after the last change, without a toast), delete, upload an image of a block and
 * preview. The pages reload the prop "documentSlides", which carries the editor.
 *
 * The preview (D-432) draws the saved content on the background of the library with Chromium, like
 * the PDF, for who may see the group; without a background on a white page.
 */
class ProposalCustomSlideController extends Controller
{
    public function store(Request $request, ProposalGroup $group, CreateCustomSlide $createCustomSlide): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $createCustomSlide->handle($group, $actor);

        return back();
    }

    public function update(CustomSlideRequest $request, ProposalGroup $group, ProposalCustomSlide $customSlide, SaveCustomSlide $saveCustomSlide): RedirectResponse
    {
        $saveCustomSlide->handle($customSlide, $request->customSlideInput(), $request->actor());

        return back();
    }

    public function destroy(Request $request, ProposalGroup $group, ProposalCustomSlide $customSlide, DeleteCustomSlide $deleteCustomSlide): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $deleteCustomSlide->handle($customSlide, $actor);

        return back()->with('toast', Toast::success(Text::get('proposals.toasts.custom_slide_deleted', ['title' => $customSlide->title])));
    }

    public function image(CustomSlideImageRequest $request, ProposalGroup $group, ProposalCustomSlide $customSlide, AttachCustomSlideImage $attachCustomSlideImage): RedirectResponse
    {
        $attachCustomSlideImage->handle($customSlide, $request->image(), $request->blockKey(), $request->actor());

        return back();
    }

    public function preview(ProposalGroup $group, ProposalCustomSlide $customSlide, DocumentComposer $composer, CustomSlideImages $images, RendersPdf $pipeline): Response
    {
        $custom = CustomSlide::fromModel($customSlide->load('section'));
        $background = null;

        foreach ($composer->forGroup($group)->slides as $composed) {
            if ($composed->custom?->id === $custom->id) {
                $background = StoredFile::query()->find($composed->slide->image_file_id);
            }
        }

        $backgroundPath = $background !== null ? Storage::disk($background->disk)->path($background->path) : null;
        $folder = storage_path('framework/custom-slide-previews/'.Str::ulid());
        File::ensureDirectoryExists($folder, 0700);
        $output = $folder.'/anteprima.png';

        try {
            $pipeline->screenshot(CustomBlocksLayout::page(
                $backgroundPath !== null && is_file($backgroundPath) ? $backgroundPath : null,
                $custom,
                $images->files($custom),
            ), $output);
            $png = (string) file_get_contents($output);
        } finally {
            File::deleteDirectory($folder);
        }

        return response($png, HttpResponse::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }
}
