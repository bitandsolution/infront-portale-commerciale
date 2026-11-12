<?php

namespace App\Http\Controllers\Content;

use App\Domain\Content\Actions\SaveLedMockupSetting;
use App\Domain\Content\Services\LedMockups;
use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Layouts\LedMockupLayout;
use App\Domain\Documents\Services\LogoStripColor;
use App\Enums\LedPhotoKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\LedMockupRequest;
use App\Models\Club;
use App\Models\LedMockupSetting;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Content\LedMockupsPresenter;
use App\Support\Documents\SampleDeck;
use App\Support\Navigation\LayoutProps;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * "Contenuti › Simulazioni LED" (D-398; DM-06): the generic configuration of Infront and one per
 * active Infront club, three kinds of photo each. Admin and manager see it, only the admin changes
 * it. The preview draws the saved photo with the areas, height and spacing of the editor (not saved
 * yet) and a sample logo, through the same pipeline as the PDF (one implementation).
 */
class LedMockupController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LedMockupSetting::class);

        return Inertia::render('Content/LedMockups/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'content_led_mockups'),
            'rows' => LedMockupsPresenter::rows(),
        ]);
    }

    public function edit(Request $request, string $scope, string $kind): Response
    {
        Gate::authorize('viewAny', LedMockupSetting::class);

        [$club, $photoKind] = self::target($scope, $kind);

        return Inertia::render('Content/LedMockups/Edit', [
            'layout' => LayoutProps::for($this->actor($request), 'content_led_mockups'),
            'mockup' => LedMockupsPresenter::editor($club, $photoKind, self::setting($club, $photoKind), $this->actor($request)),
        ]);
    }

    public function update(LedMockupRequest $request, string $scope, string $kind, SaveLedMockupSetting $save): RedirectResponse
    {
        [$club, $photoKind] = self::target($scope, $kind);

        $photo = $request->file('photo');
        $save->handle($club, $photoKind, $photo instanceof UploadedFile ? $photo : null, $request->areas(), $request->integer('logo_height_percent'), $request->integer('spacing'), $request->actor());

        return back()->with('toast', Toast::success(Text::get('content.toasts.led_mockup_saved', [
            'name' => $club !== null ? $club->name : Text::get('content.led_mockups.generic'),
            'photo' => $photoKind->label(),
        ])));
    }

    /**
     * PNG of the page with the saved photo and the values of the editor (query: areas as JSON,
     * logo_height_percent, spacing, format), drawn with a sample logo.
     */
    public function preview(LedMockupRequest $request, string $scope, string $kind, RendersPdf $pipeline): HttpResponse
    {
        [$club, $photoKind] = self::target($scope, $kind);
        $setting = self::setting($club, $photoKind);
        $photo = $setting !== null ? StoredFile::query()->find($setting->photo_file_id) : null;

        if ($photo === null || count($request->areas()) !== $photoKind->areaCount()) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $folder = storage_path('framework/led-previews/'.Str::ulid());
        File::ensureDirectoryExists($folder, 0700);
        $logoPath = $folder.'/logo.png';
        file_put_contents($logoPath, SampleDeck::clientLogo());
        $size = getimagesize($logoPath);
        [$logoWidth, $logoHeight] = $size !== false ? [$size[0], $size[1]] : [1, 1];
        $format = $request->string('format')->toString();
        $output = $folder.'/anteprima.png';

        try {
            $pipeline->screenshot(LedMockupLayout::page(
                photoPath: Storage::disk($photo->disk)->path($photo->path),
                photoWidth: (int) $photo->width,
                photoHeight: (int) $photo->height,
                areas: $request->areas(),
                style: LedMockups::styleForFormat($format),
                logoPath: $logoPath,
                logoWidth: (int) $logoWidth,
                logoHeight: (int) $logoHeight,
                color: LogoStripColor::of($logoPath),
                logoHeightPercent: $request->integer('logo_height_percent'),
                spacing: $request->integer('spacing'),
            ), $output);
            $png = (string) file_get_contents($output);
        } finally {
            File::deleteDirectory($folder);
        }

        return response($png, HttpResponse::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array{Club|null, LedPhotoKind}
     */
    private static function target(string $scope, string $kind): array
    {
        $photoKind = LedPhotoKind::tryFrom($kind) ?? abort(HttpResponse::HTTP_NOT_FOUND);
        $club = $scope === LedMockupsPresenter::GENERIC
            ? null
            : Club::query()->whereKey((int) $scope)->where('is_infront', true)->firstOrFail();

        return [$club, $photoKind];
    }

    private static function setting(?Club $club, LedPhotoKind $kind): ?LedMockupSetting
    {
        return LedMockupSetting::query()
            ->with('photo')
            ->where('club_slot', $club !== null ? $club->id : 0)
            ->where('photo_kind', $kind->value)
            ->first();
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
