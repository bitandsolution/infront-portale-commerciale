<?php

use App\Domain\Documents\Contracts\RendersPdf;
use App\Domain\Documents\Services\DocumentGenerator;
use App\Domain\Proposals\Actions\SubmitGroup;
use App\Enums\DocumentLayout;
use App\Enums\DocumentNotification;
use App\Enums\DocumentStatus;
use App\Enums\FilePurpose;
use App\Enums\NotificationType;
use App\Enums\SlideDocumentType;
use App\Jobs\GenerateDocument;
use App\Mail\ProposalMail;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\Document;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\NotificationLog;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakePdfRenderer;

/*
| E-mails of the proposals (M01 §7, M07 F-08, M07-CA5, M01-CA6; D-105, D-221, D-299, D-474…D-478;
| slice F5, PR F5-06): sending for approval to the active admins and managers; approval and new version
| to the agent when the PDF is ready, or without it and with the alert to the admins when it fails;
| "document ready" after a successful regeneration; rejection and cancellation with the reason. One
| e-mail per group, no amount, no attachment, the link to the detail of the group.
|
| Queue::fake() keeps the job of the document on hold, so the e-mails before and after it are seen.
*/

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();

    $this->lazio = Club::factory()->infront()->create([
        'name' => 'SS Lazio',
        'code' => 'LAZIO',
        'short_name' => 'Lazio',
        'logo_file_id' => storeTestFile(FilePurpose::ClubLogo, null, null, 'logo.png')->id,
    ]);
    ClubEnrollment::query()->create(['competition_season_id' => $league->id, 'club_id' => $this->lazio->id]);
    LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $this->lazio->id, 'led_format_id' => $this->format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->lazio->id, 'inventory_pool_id' => InventoryPool::query()->where('code', 'FILA_2')->value('id'), 'is_enabled' => true, 'seconds_per_match' => 600]);

    $this->agent = User::factory()->agent()->create(['first_name' => 'Luca', 'last_name' => 'Verdi']);
    $this->admin = User::factory()->admin()->create(['first_name' => 'Anna', 'last_name' => 'Neri']);
    $this->manager = User::factory()->manager()->create(['first_name' => 'Marta', 'last_name' => 'Bruni']);
    User::factory()->manager()->inactive()->create();
    User::factory()->manager()->create(['activated_at' => null]);
});

/** Cover, club recap and closing of the club: with them the document of the group is ready. */
function mailSlides(object $test): void
{
    Slide::factory()->forSeason($test->season)->forClub($test->lazio)->section('INTRO')->layout(DocumentLayout::Cover)->create(['title' => 'Copertina Lazio']);
    Slide::factory()->forSeason($test->season)->forClub($test->lazio)->section('RECAP')->layout(DocumentLayout::ClubRecap)->document(SlideDocumentType::Club)->create(['title' => 'Recap Lazio']);
    Slide::factory()->forSeason($test->season)->forClub($test->lazio)->section('CLOSING')->create(['title' => 'Contatti Lazio']);
}

function mailSave(User $actor, Proposal $proposal, int $seconds, string $route = 'proposals.update', string $page = 'proposals.edit'): void
{
    $proposal->refresh();
    $version = $proposal->workingVersion;

    test()->actingAs($actor)
        ->from(route($page, ['proposal' => $proposal, 'passo' => 4]))
        ->put(route($route, $proposal), [
            'lock_version' => $proposal->lock_version,
            'client_id' => $proposal->client_id,
            'season_id' => $proposal->season_id,
            'starts_on' => $version?->starts_on?->toDateString(),
            'ends_on' => $version?->ends_on?->toDateString(),
            'clubs' => [[
                'club_id' => test()->lazio->id,
                'partnership_tier_id' => null,
                'led_format_id' => test()->format->id,
                'led_seconds' => $seconds,
                'seats' => [],
            ]],
        ])
        ->assertSessionHasNoErrors();
}

/**
 * A group sent for approval, one variant per entry of $seconds (A, B…).
 *
 * @param  list<int>  $seconds
 */
function mailSubmitted(object $test, array $seconds = [60]): Proposal
{
    $client = Client::factory()->ownedBy($test->agent)->create(['company_name' => 'Birra Aurora S.p.A.']);
    test()->actingAs($test->agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $first = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    mailSave($test->agent, $first, $seconds[0]);

    foreach (array_slice($seconds, 1) as $variantSeconds) {
        test()->actingAs($test->agent)
            ->from(route('proposals.edit', ['proposal' => $first, 'passo' => 5]))
            ->post(route('proposals.variants.store', $first), ['step' => 5])
            ->assertSessionHasNoErrors();
        mailSave($test->agent, Proposal::query()->where('proposal_group_id', $first->proposal_group_id)->latest('id')->firstOrFail(), $variantSeconds);
    }

    test()->actingAs($test->agent)->post(route('proposals.groups.submit', $first->proposal_group_id))->assertSessionHasNoErrors();

    return $first->refresh();
}

function mailApproved(object $test, array $seconds = [60]): Proposal
{
    $proposal = mailSubmitted($test, $seconds);
    test()->actingAs($test->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.approve', $proposal->proposal_group_id))
        ->assertSessionHasNoErrors();

    return $proposal->refresh();
}

/** Runs the job of the current document of the group, as the queue "documents" does. */
function mailGenerate(Proposal $proposal): Document
{
    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->whereNull('superseded_at')->sole();
    app(DocumentGenerator::class)->generate($document->id);

    return $document->refresh();
}

/**
 * The e-mails of the proposals queued so far, as [type, recipient id].
 *
 * @return list<array{0: NotificationType, 1: int}>
 */
function mailSent(): array
{
    return NotificationLog::query()->orderBy('id')->get()
        ->map(fn (NotificationLog $log): array => [$log->type, $log->user_id])
        ->all();
}

function mailOf(NotificationType $type, User $to): ProposalMail
{
    $found = null;
    Mail::assertQueued(ProposalMail::class, function (ProposalMail $mail) use ($type, $to, &$found): bool {
        if ($mail->type === $type && $mail->hasTo($to->email)) {
            $found = $mail;

            return true;
        }

        return false;
    });
    assert($found instanceof ProposalMail);

    return $found;
}

it('M01-CA6, M07-CA5: the sending reaches the active admins and managers, with the link and no attachment', function () {
    $proposal = mailSubmitted($this);

    expect(mailSent())->toBe([
        [NotificationType::ProposalSubmitted, $this->admin->id],
        [NotificationType::ProposalSubmitted, $this->manager->id],
    ])->and(NotificationLog::query()->pluck('proposal_id')->unique()->all())->toBe([$proposal->id]);

    $mail = mailOf(NotificationType::ProposalSubmitted, $this->manager);
    $mail->assertHasSubject('Nuova proposta da approvare: n. '.$proposal->number);
    $mail->assertSeeInHtml('Ciao Marta,');
    $mail->assertSeeInHtml('Luca Verdi ha inviato in approvazione la proposta n. '.$proposal->number.'.');
    $mail->assertSeeInHtml('Birra Aurora S.p.A.');
    $mail->assertSeeInHtml('2027-28');
    $mail->assertSeeInHtml(route('proposals.groups.show', $proposal->proposal_group_id), false);
    $mail->assertDontSeeInHtml('€');
    expect($mail->attachments())->toBe([]);
});

it('D-475, D-478: one e-mail per group with the letters; "sent again" after a rejection', function () {
    $proposal = mailSubmitted($this, [60, 120]);
    expect(mailSent())->toHaveCount(2);
    expect(mailOf(NotificationType::ProposalSubmitted, $this->admin)->details['Varianti'])->toBe('A, B');

    $this->actingAs($this->manager)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.reject', $proposal->proposal_group_id), ['reason' => "Manca il\nlogo | del cliente"])
        ->assertSessionHasNoErrors();

    $rejected = mailOf(NotificationType::ProposalRejected, $this->agent);
    $rejected->assertHasSubject('Proposta n. '.$proposal->number.' rifiutata');
    $rejected->assertSeeInHtml('Marta Bruni');
    $rejected->assertSeeInHtml('Manca il logo / del cliente');
    $rejected->assertSeeInHtml('mantiene lo stesso numero');

    $this->actingAs($this->agent)->post(route('proposals.groups.submit', $proposal->proposal_group_id))->assertSessionHasNoErrors();

    mailOf(NotificationType::ProposalSubmitted, $this->admin);
    Mail::assertQueued(ProposalMail::class, fn (ProposalMail $mail): bool => $mail->subjectText() === 'Proposta n. '.$proposal->number.' inviata di nuovo in approvazione' && $mail->hasTo($this->admin->email));
    expect(mailSent())->toHaveCount(5);
});

it('M07-CA5: a rejection without a reason says so', function () {
    $proposal = mailSubmitted($this);

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.reject', $proposal->proposal_group_id))
        ->assertSessionHasNoErrors();

    mailOf(NotificationType::ProposalRejected, $this->agent)->assertSeeInHtml('Nessuna motivazione indicata');
});

it('D-221: the approval e-mail leaves when the PDF is ready, with the link', function () {
    mailSlides($this);
    $proposal = mailApproved($this);
    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->sole();

    Queue::assertPushedOn('documents', GenerateDocument::class);
    expect($document->pending_notification)->toBe(DocumentNotification::Approved)
        ->and(collect(mailSent())->contains(fn (array $sent): bool => $sent[0] === NotificationType::ProposalApproved))->toBeFalse();

    $document = mailGenerate($proposal);

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->pending_notification)->toBeNull();

    $mail = mailOf(NotificationType::ProposalApproved, $this->agent);
    $mail->assertHasSubject('Proposta n. '.$proposal->number.' approvata');
    $mail->assertSeeInHtml('Ciao Luca,');
    $mail->assertSeeInHtml('Anna Neri');
    $mail->assertSeeInHtml('Il documento PDF è pronto');
    $mail->assertSeeInHtml(route('proposals.groups.show', $proposal->proposal_group_id), false);
    $mail->assertDontSeeInHtml('€');
    expect($mail->attachments())->toBe([])
        ->and(NotificationLog::query()->where('type', NotificationType::ProposalApproved->value)->sole()->proposal_id)->toBe($proposal->id);

    // A regeneration of a document already delivered sends nothing (D-477).
    $count = count(mailSent());
    $this->actingAs($this->admin)->post(route('documents.regenerate', $proposal->proposal_group_id))->assertSessionHasNoErrors();
    mailGenerate($proposal);
    expect(mailSent())->toHaveCount($count);
});

it('D-221, D-476, D-477: a failed PDF tells the agent without it and the admins; the regeneration then sends "document ready"', function () {
    $proposal = mailApproved($this);
    $document = mailGenerate($proposal);

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->pending_notification)->toBe(DocumentNotification::DocumentReady);

    $approved = mailOf(NotificationType::ProposalApproved, $this->agent);
    $approved->assertSeeInHtml('Il documento PDF non è stato generato');
    $approved->assertDontSeeInHtml('Il documento PDF è pronto');

    $failed = mailOf(NotificationType::ProposalDocumentFailed, $this->admin);
    $failed->assertHasSubject('Documento non generato: proposta n. '.$proposal->number);
    $failed->assertSeeInHtml('Rigenera');
    $failed->assertSeeInHtml('Luca Verdi');
    Mail::assertNotQueued(ProposalMail::class, fn (ProposalMail $mail): bool => $mail->type === NotificationType::ProposalDocumentFailed && $mail->hasTo($this->manager->email));

    mailSlides($this);
    $this->actingAs($this->admin)->post(route('documents.regenerate', $proposal->proposal_group_id))->assertSessionHasNoErrors();
    $document = mailGenerate($proposal);

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->pending_notification)->toBeNull();
    mailOf(NotificationType::ProposalDocumentReady, $this->agent)
        ->assertHasSubject('Documento pronto: proposta n. '.$proposal->number);
});

it('D-476: after the last attempt of the job the admins are told once', function () {
    mailSlides($this);
    $proposal = mailApproved($this);
    $document = Document::query()->where('proposal_group_id', $proposal->proposal_group_id)->sole();
    $renderer = app(RendersPdf::class);
    assert($renderer instanceof FakePdfRenderer);
    $renderer->failWith = 'Chromium non risponde';

    expect(fn () => app(DocumentGenerator::class)->generate($document->id))->toThrow(RuntimeException::class);
    (new GenerateDocument($document->id))->failed(new RuntimeException('Chromium non risponde'));
    (new GenerateDocument($document->id))->failed(new RuntimeException('Chromium non risponde'));

    expect(collect(mailSent())->filter(fn (array $sent): bool => $sent[0] === NotificationType::ProposalDocumentFailed)->count())->toBe(1)
        ->and(collect(mailSent())->filter(fn (array $sent): bool => $sent[0] === NotificationType::ProposalApproved)->count())->toBe(1);
    mailOf(NotificationType::ProposalDocumentFailed, $this->admin)->assertDontSeeInHtml('Chromium');
});

it('D-474: the new version e-mail leaves with the regenerated PDF and names the version', function () {
    mailSlides($this);
    $proposal = mailApproved($this);
    mailGenerate($proposal);

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.revision.start', $proposal))
        ->assertSessionHasNoErrors();
    mailSave($this->admin, $proposal, 120, 'proposals.revision.update', 'proposals.revision.edit');
    $this->actingAs($this->admin)
        ->from(route('proposals.revision.edit', ['proposal' => $proposal, 'passo' => 5]))
        ->post(route('proposals.revision.save', $proposal), ['lock_version' => $proposal->refresh()->lock_version])
        ->assertSessionHasNoErrors();

    Mail::assertNotQueued(ProposalMail::class, fn (ProposalMail $mail): bool => $mail->type === NotificationType::ProposalNewVersion);
    mailGenerate($proposal);

    $mail = mailOf(NotificationType::ProposalNewVersion, $this->agent);
    $mail->assertHasSubject('Proposta n. '.$proposal->number.': nuova versione 2');
    $mail->assertSeeInHtml('ne ha salvato la versione 2');
    $mail->assertSeeInHtml('Anna Neri');
    $mail->assertSeeInHtml('Il documento PDF aggiornato è pronto');
});

it('D-472, D-478: the cancellation tells the agent with the reason; a PDF still pending then sends nothing', function () {
    mailSlides($this);
    $proposal = mailApproved($this);

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.cancel', $proposal->proposal_group_id), ['cancel_reason' => 'Il cliente ha ritirato il budget'])
        ->assertSessionHasNoErrors();

    $mail = mailOf(NotificationType::ProposalCancelled, $this->agent);
    $mail->assertHasSubject('Proposta n. '.$proposal->number.' annullata');
    $mail->assertSeeInHtml('Il cliente ha ritirato il budget');
    $mail->assertSeeInHtml('Anna Neri');
    $mail->assertSeeInHtml('duplica una variante');

    // The document asked by the approval becomes ready after the cancellation: no approval e-mail.
    mailGenerate($proposal);
    Mail::assertNotQueued(ProposalMail::class, fn (ProposalMail $mail): bool => $mail->type === NotificationType::ProposalApproved);
});

it('D-473: after the choice of the client the cancellation names only the chosen variant', function () {
    mailSlides($this);
    $a = mailApproved($this, [60, 120]);
    $b = Proposal::query()->where('proposal_group_id', $a->proposal_group_id)->where('variant_letter', 'B')->firstOrFail();
    $this->actingAs($this->admin)->post(route('proposals.groups.choose', $a->proposal_group_id), ['proposal_id' => $b->id])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('proposals.groups.cancel', $a->proposal_group_id), ['cancel_reason' => 'Il cliente ha ritirato il budget'])
        ->assertSessionHasNoErrors();

    $mail = mailOf(NotificationType::ProposalCancelled, $this->agent);
    expect($mail->details['Varianti'])->toBe('B');
    expect(NotificationLog::query()->where('type', NotificationType::ProposalCancelled->value)->sole()->proposal_id)->toBe($a->id);
});

it('D-475: a deactivated agent gets no e-mail; the other recipients still do', function () {
    $proposal = mailSubmitted($this);
    $this->agent->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.reject', $proposal->proposal_group_id), ['reason' => 'Prezzi da rivedere'])
        ->assertSessionHasNoErrors();

    Mail::assertNotQueued(ProposalMail::class, fn (ProposalMail $mail): bool => $mail->type === NotificationType::ProposalRejected);
});

it('D-299: an Action rolled back leaves no row in the register', function () {
    $client = Client::factory()->ownedBy($this->agent)->create();
    $this->actingAs($this->agent)->post(route('proposals.store'), ['client_id' => $client->id]);
    $proposal = Proposal::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    mailSave($this->agent, $proposal, 60);
    $group = ProposalGroup::query()->findOrFail($proposal->proposal_group_id);

    try {
        DB::transaction(function () use ($group): void {
            app(SubmitGroup::class)->handle($group, $this->agent);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(NotificationLog::query()->count())->toBe(0);
});

it('M07 RB-08: a rejected proposal with e-mails in the register can still be deleted by its agent', function () {
    $proposal = mailSubmitted($this);
    $this->actingAs($this->admin)
        ->from(route('proposals.groups.show', $proposal->proposal_group_id))
        ->post(route('proposals.groups.reject', $proposal->proposal_group_id))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->agent)
        ->from(route('pages.proposals_mine'))
        ->delete(route('proposals.destroy', $proposal))
        ->assertSessionHasNoErrors();

    expect(Proposal::query()->whereKey($proposal->id)->exists())->toBeFalse()
        ->and(NotificationLog::query()->count())->toBe(3)
        ->and(NotificationLog::query()->whereNotNull('proposal_id')->count())->toBe(0);
});
