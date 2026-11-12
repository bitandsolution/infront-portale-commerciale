<?php

use App\Domain\Content\Services\LedMockups;
use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Data\LayoutPage;
use App\Domain\Documents\Layouts\LedMockupLayout;
use App\Domain\Documents\Services\LogoStripColor;
use App\Enums\AuditAction;
use App\Enums\DocumentLayout;
use App\Enums\FilePurpose;
use App\Enums\LedPhotoKind;
use App\Models\Club;
use App\Models\LedMockupSetting;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Documents\SampleDeck;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakePdfRenderer;

/*
| Simulation of the logo on the LED (D-398, D-423…D-427; slice DM, PR DM-06): the configuration of
| "Contenuti › Simulazioni LED" (generic of Infront and per club, three kinds of photo), the choice
| of the configuration for a club and a format, the colour of the strip and the geometry of the
| layout. The pages in the documents are in DocumentSlidesTest; the rendering in the visual
| regression (group "pdf"); the routes in the route × role matrix.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->seed(SystemDataSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->udinese = Club::factory()->infront()->create(['name' => 'Udinese Calcio', 'code' => 'UDINESE']);
});

/** Upload of a field photo of 1768×726 (SampleDeck). */
function ledmPhoto(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('campo.png', SampleDeck::fieldPhoto());
}

/** @return list<list<array{int, int}>> */
function ledmBothRows(): array
{
    return [
        [[0, 180], [1768, 180], [1768, 218], [0, 218]],
        [[0, 224], [1768, 224], [1768, 262], [0, 262]],
    ];
}

/**
 * @param  list<list<array{int, int}>>  $areas
 */
function ledmSave(User $actor, string $scope, string $kind, array $areas, ?UploadedFile $photo = null, int $height = 85, int $spacing = 30): TestResponse
{
    $payload = ['areas' => json_encode($areas), 'logo_height_percent' => $height, 'spacing' => $spacing];

    if ($photo !== null) {
        $payload['photo'] = $photo;
    }

    return test()->actingAs($actor)
        ->from(route('content.led-mockups.edit', ['scope' => $scope, 'kind' => $kind]))
        ->post(route('content.led-mockups.update', ['scope' => $scope, 'kind' => $kind]), $payload);
}

it('D-398: the admin saves the generic photo of Jumbo and Domination with two areas; one audit event', function () {
    ledmSave($this->admin, 'infront', 'both_rows', ledmBothRows(), ledmPhoto())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', 'Simulazione LED salvata: Generica Infront, Jumbo e Domination');

    $setting = LedMockupSetting::query()->sole();
    $photo = StoredFile::query()->findOrFail($setting->photo_file_id);

    expect($setting->club_id)->toBeNull()
        ->and($setting->photo_kind)->toBe(LedPhotoKind::BothRows)
        ->and($setting->areas)->toBe(ledmBothRows())
        ->and([$setting->logo_height_percent, $setting->spacing])->toBe([85, 30])
        ->and($photo->purpose)->toBe(FilePurpose::FieldPhoto)
        ->and([$photo->width, $photo->height])->toBe([1768, 726]);

    $event = soleAuditEvent(AuditAction::LedMockupSaved);
    expect($event->object_label)->toBe('Generica Infront · Jumbo e Domination')
        ->and($event->new_values)->toMatchArray(['club_name' => 'Generica Infront', 'photo_kind' => 'both_rows', 'logo_height_percent' => 85, 'spacing' => 30]);

    // Same values, no new photo: nothing written, no event; a new spacing: one event with it.
    ledmSave($this->admin, 'infront', 'both_rows', ledmBothRows())->assertSessionHasNoErrors();
    expect(auditEventsOf(AuditAction::LedMockupSaved))->toHaveCount(1);

    ledmSave($this->admin, 'infront', 'both_rows', ledmBothRows(), null, 85, 40)->assertSessionHasNoErrors();
    expect(auditEventsOf(AuditAction::LedMockupSaved)->last()?->new_values)->toBe(['club_name' => 'Generica Infront', 'photo_kind' => 'both_rows', 'spacing' => 40])
        ->and(LedMockupSetting::query()->sole()->photo_file_id)->toBe($photo->id);
});

it('D-398: the photo is needed at the first save, the areas must be as many as the kind wants and inside the photo', function () {
    ledmSave($this->admin, (string) $this->udinese->id, 'row_1', [ledmBothRows()[0]])
        ->assertSessionHasErrors(['photo' => 'Carica la foto del campo.']);

    ledmSave($this->admin, (string) $this->udinese->id, 'row_1', ledmBothRows(), ledmPhoto())
        ->assertSessionHasErrors(['areas' => "Segna l'area del LED con quattro punti."]);

    ledmSave($this->admin, (string) $this->udinese->id, 'row_1', [[[0, 180], [1900, 180], [1768, 218], [0, 218]]], ledmPhoto())
        ->assertSessionHasErrors(['areas' => 'I punti del LED devono stare dentro la foto.']);

    expect(LedMockupSetting::query()->count())->toBe(0)
        ->and(StoredFile::query()->where('purpose', FilePurpose::FieldPhoto->value)->count())->toBe(0);
});

it('D-398: admin and manager see the page; only the admin saves and previews', function () {
    ledmSave($this->admin, (string) $this->udinese->id, 'row_2', [ledmBothRows()[0]], ledmPhoto())->assertSessionHasNoErrors();

    $this->actingAs($this->manager)->get(route('pages.content_led_mockups'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Content/LedMockups/Index')
            ->where('rows.0.name', 'Generica Infront')
            ->where('rows.0.kinds.1.configured', false)
            ->where('rows.1.name', 'Udinese Calcio')
            ->where('rows.1.kinds.1.kind', 'row_2')
            ->where('rows.1.kinds.1.configured', true));

    $this->actingAs($this->manager)->get(route('content.led-mockups.edit', ['scope' => $this->udinese->id, 'kind' => 'row_2']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Content/LedMockups/Edit')
            ->where('mockup.canEdit', false)
            ->where('mockup.areaCount', 1)
            ->where('mockup.photo.width', 1768)
            ->where('mockup.areas', [ledmBothRows()[0]]));

    ledmSave($this->manager, (string) $this->udinese->id, 'row_2', [ledmBothRows()[0]])->assertForbidden();

    // A club that is not of Infront has no configuration.
    $other = Club::factory()->create();
    $this->actingAs($this->admin)->get(route('content.led-mockups.edit', ['scope' => $other->id, 'kind' => 'row_1']))->assertNotFound();
});

it('D-398: the preview draws the saved photo with the values on the screen and a sample logo', function () {
    ledmSave($this->admin, 'infront', 'both_rows', ledmBothRows(), ledmPhoto())->assertSessionHasNoErrors();

    $response = $this->actingAs($this->admin)->get(route('content.led-mockups.preview', [
        'scope' => 'infront',
        'kind' => 'both_rows',
        'areas' => json_encode(ledmBothRows()),
        'logo_height_percent' => 90,
        'spacing' => 12,
        'format' => 'DOMINATION',
    ]))->assertOk();

    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);
    $page = $renderer->screenshots[0];
    assert($page instanceof LayoutPage);

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($page->layout)->toBe(DocumentLayout::LedMockup)
        ->and($page->backgroundPath)->toBeNull()
        ->and($page->data['style'])->toBe(LedMockupLayout::STYLE_DOMINATION)
        ->and($page->data['logo_height_percent'])->toBe(90)
        ->and($page->data['spacing'])->toBe(12)
        // The sample logo is transparent around: black strip (D-425).
        ->and($page->data['color'])->toBe(LogoStripColor::BLACK);

    $this->actingAs($this->manager)->get(route('content.led-mockups.preview', ['scope' => 'infront', 'kind' => 'both_rows', 'areas' => json_encode(ledmBothRows()), 'logo_height_percent' => 90, 'spacing' => 12]))
        ->assertForbidden();
});

it('D-427: the club configuration wins over the generic one; without both there is none', function () {
    ledmSave($this->admin, 'infront', 'row_1', [ledmBothRows()[1]], ledmPhoto())->assertSessionHasNoErrors();
    ledmSave($this->admin, (string) $this->udinese->id, 'row_1', [ledmBothRows()[1]], ledmPhoto(), 70)->assertSessionHasNoErrors();
    $roma = Club::factory()->infront()->create();

    $mockups = app(LedMockups::class);

    expect($mockups->settingFor($this->udinese->id, 'FILA_1')?->logo_height_percent)->toBe(70)
        ->and($mockups->settingFor($roma->id, 'FILA_1')?->club_id)->toBeNull()
        ->and($mockups->settingFor($roma->id, 'FILA_2'))->toBeNull()
        ->and($mockups->settingFor($roma->id, 'JUMBO'))->toBeNull()
        ->and($mockups->kindForFormat('DOMINATION'))->toBe(LedPhotoKind::BothRows)
        ->and($mockups->kindForFormat('FILA_2'))->toBe(LedPhotoKind::Row2)
        ->and(LedMockups::styleForFormat('DOMINATION'))->toBe(LedMockupLayout::STYLE_DOMINATION)
        ->and(LedMockups::styleForFormat('JUMBO'))->toBe(LedMockupLayout::STYLE_ROWS);
});

it('D-425: the strip takes the colour of the edges of the logo, black when they are transparent', function () {
    $folder = storage_path('framework/testing/led-colour');
    @mkdir($folder, 0777, true);
    file_put_contents($folder.'/opaco.png', SampleDeck::opaqueLogo());
    file_put_contents($folder.'/trasparente.png', SampleDeck::clientLogo());

    expect(LogoStripColor::of($folder.'/opaco.png'))->toBe('#C8102E')
        ->and(LogoStripColor::of($folder.'/trasparente.png'))->toBe('#000000')
        ->and(LogoStripColor::of($folder.'/manca.png'))->toBe('#000000');
});

it('D-398: the layout brings each strip onto the four points; Domination is one strip with the line', function () {
    $quad = [[100, 200], [1700, 180], [1750, 260], [60, 300]];
    $values = array_map('floatval', explode(',', substr(LedMockupLayout::matrix3d($quad, 1600, 100), 9, -1)));

    // The four corners of the rectangle 1600 × 100 land on the four points.
    foreach ([[0, 0, 0], [1600, 0, 1], [1600, 100, 2], [0, 100, 3]] as [$x, $y, $corner]) {
        $w = $values[3] * $x + $values[7] * $y + $values[15];
        expect(round(($values[0] * $x + $values[4] * $y + $values[12]) / $w, 1))->toBe((float) $quad[$corner][0])
            ->and(round(($values[1] * $x + $values[5] * $y + $values[13]) / $w, 1))->toBe((float) $quad[$corner][1]);
    }

    $page = fn (string $style): array => LedMockupLayout::viewData(LedMockupLayout::page('/campo.jpg', 1768, 726, ledmBothRows(), $style, '/logo.png', 400, 100, '#C8102E', 80, 30)->data, fn (string $path): string => basename($path));
    $rows = $page(LedMockupLayout::STYLE_ROWS);
    $domination = $page(LedMockupLayout::STYLE_DOMINATION);

    expect($rows['frame'])->toMatchArray(['width' => '1768', 'height' => '726'])
        ->and($rows['photo'])->toBe('campo.jpg')
        ->and($rows['strips'])->toHaveCount(2)
        ->and($rows['strips'][0]['color'])->toBe('#C8102E')
        // Logo 80% of 38 px = 30.4 high, 121.6 wide; the first one cut in half.
        ->and($rows['strips'][0]['logos'][0])->toBe(['left' => '-60.8', 'top' => '3.8', 'width' => '121.6', 'height' => '30.4'])
        ->and($rows['strips'][0]['line'])->toBeNull()
        ->and($domination['strips'])->toHaveCount(1)
        ->and($domination['strips'][0]['height'])->toBe('82')
        ->and($domination['strips'][0]['line'])->toMatchArray(['color' => LedMockupLayout::LINE_COLOR]);
});
