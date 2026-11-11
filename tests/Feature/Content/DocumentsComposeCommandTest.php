<?php

use App\Enums\DocumentLayout;
use App\Enums\SlideDocumentType;
use App\Models\Club;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Slide;
use App\Models\StoredFile;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
| documents:compose (DM-02): read-only check of the composition of the document of a group.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->seed(SystemDataSeeder::class);
});

it('DM-02: shows the document type, the slides with their pages and the warnings', function () {
    $proposal = Proposal::factory()->withWorkingVersion()->create();
    $club = Club::factory()->infront()->create(['short_name' => 'Lazio']);
    ProposalVersionClub::factory()->create([
        'proposal_version_id' => ProposalVersion::query()->where('proposal_id', $proposal->id)->value('id'),
        'club_id' => $club->id,
    ]);
    $season = $proposal->season()->firstOrFail();
    Slide::factory()->forSeason($season)->forClub($club)->document(SlideDocumentType::Club)->layout(DocumentLayout::ClubRecap)
        ->section('RECAP')->create(['title' => 'Richiesta economica']);
    Slide::factory()->forSeason($season)->forClub($club)->document(SlideDocumentType::Club)->section('CLOSING')->create(['title' => 'Grazie']);

    $status = Artisan::call('documents:compose', ['number' => (string) $proposal->number]);
    $output = Artisan::output();

    expect($status)->toBe(0)
        ->and($output)->toContain('Proposta n. '.$proposal->number.' · gruppo con 1 variante/i · stagione '.$season->code.' · Documento club')
        ->and($output)->toContain('Club del gruppo: Lazio')
        ->and($output)->toContain('Richiesta economica')
        ->and($output)->toContain('Proposta A')
        ->and($output)->toContain('2 slide della libreria, 2 pagine previste.')
        ->and($output)->toContain('- Manca la copertina attiva per il documento club');
});

it('DM-02: an unknown proposal is an error', function () {
    $status = Artisan::call('documents:compose', ['number' => '99999']);

    expect($status)->toBe(1)
        ->and(Artisan::output())->toContain('Proposta n. 99999 non trovata.');
});
