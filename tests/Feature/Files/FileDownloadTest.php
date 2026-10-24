<?php

use App\Enums\FilePurpose;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Files\SampleFiles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
| Download of the stored files (D-218, D-215): only from the route with the Policy, with safe
| headers. Until the slices that use the files, only the admin downloads (StoredFilePolicy).
| The answer for every kind of user is also in the route matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

it('D-218: the admin downloads a logo, shown in the browser, with safe headers', function () {
    $admin = User::factory()->admin()->create();
    $file = storeTestFile(FilePurpose::ClientLogo, null, null, 'Logo Società.png');

    $response = $this->actingAs($admin)->get(route('files.download', $file));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
        ->assertHeader('ETag', '"'.$file->sha256.'"')
        ->assertHeader('Content-Length', (string) $file->size_bytes);

    $disposition = (string) $response->headers->get('Content-Disposition');
    $cache = (string) $response->headers->get('Cache-Control');

    expect($disposition)->toStartWith('inline;')
        ->toContain('filename="Logo Societa.png"')
        ->toContain("filename*=utf-8''Logo%20Societ%C3%A0.png")
        ->and($cache)->toContain('private')->toContain('no-cache')
        ->and($response->streamedContent())->toBe(Storage::disk(StoredFile::DISK)->get($file->path));
});

it('D-218: an Excel file is saved, not opened', function () {
    $admin = User::factory()->admin()->create();
    $file = storeTestFile(FilePurpose::CalendarImport, null, SampleFiles::xlsx(), 'calendario.xlsx');

    $response = $this->actingAs($admin)->get(route('files.download', $file));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

    expect((string) $response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->toContain('calendario.xlsx');
});

it('M09: a generated PDF opens in the browser viewer, without the sandbox', function () {
    $admin = User::factory()->admin()->create();
    $file = storeTestFile(FilePurpose::GeneratedPdf, null, SampleFiles::pdf(), null);

    $response = $this->actingAs($admin)->get(route('files.download', $file));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');

    expect((string) $response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->toContain('generated-pdf-'.$file->id.'.pdf');
});

it('D-218: an unchanged file answers 304 Not Modified, without content', function () {
    $admin = User::factory()->admin()->create();
    $file = storeTestFile(FilePurpose::ClientLogo);

    $response = $this->actingAs($admin)
        ->get(route('files.download', $file), ['If-None-Match' => '"'.$file->sha256.'"']);

    $response->assertStatus(304)
        ->assertHeader('ETag', '"'.$file->sha256.'"');
    expect($response->getContent())->toBe('');
});

it('D-218: a revoked permission applies even with the file in the browser cache', function () {
    $manager = User::factory()->manager()->create();
    $file = storeTestFile(FilePurpose::ClientLogo);

    $this->actingAs($manager)
        ->get(route('files.download', $file), ['If-None-Match' => '"'.$file->sha256.'"'])
        ->assertForbidden();
});

it('D-215: agent and manager cannot download files until the slices that use them', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $file = storeTestFile(FilePurpose::SlideImage, null, SampleFiles::png(1920, 1080));

    $this->actingAs($user)->get(route('files.download', $file))->assertForbidden();
})->with(['agent', 'manager']);

it('D-218: a file missing from the disk answers 404 and is logged without personal data', function () {
    Log::spy();
    $admin = User::factory()->admin()->create();
    $file = storeTestFile(FilePurpose::ClientLogo);
    Storage::disk(StoredFile::DISK)->delete($file->path);

    $this->actingAs($admin)->get(route('files.download', $file))->assertNotFound();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $context === ['stored_file_id' => $file->id])
        ->once();
});

it('D-218: an unknown file answers 404', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('files.download', 999999))->assertNotFound();
});

it('D-218: the private disk is never published by Laravel at storage/{path}', function () {
    expect(config('filesystems.disks.local.serve'))->toBeFalse()
        ->and(config('filesystems.disks.files.serve'))->toBeFalse()
        ->and(collect(app('router')->getRoutes()->getRoutes())->map->uri()->all())->not->toContain('storage/{path}');
});
