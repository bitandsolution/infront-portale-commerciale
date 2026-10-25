<?php

use App\Domain\Notifications\Services\Notifications;
use App\Enums\AccessLinkType;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Mail\AccessLinkMail;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/*
| Register of the e-mails (M01 §10; D-209; progettazione-tecnica §5.3.6, §5.5.1, §5.5.4; PR-08):
| every e-mail goes through the "mail" queue, encrypted, and leaves a row in notification_logs that
| follows its delivery (queued, sent, failed), without content, link or token.
|
| In the tests the queue is "sync" and the mailer "array" (phpunit.xml): a queued e-mail is sent at
| once, after the commit of the surrounding transaction.
*/

const NOTIFICATIONS_SECRET_TOKEN = 'TokenSegretoDelLinkDiProva0123456789';

function notificationsLinkMail(string $firstName = 'Mario'): AccessLinkMail
{
    return new AccessLinkMail(
        firstName: $firstName,
        type: AccessLinkType::Activation,
        url: url('attivazione/'.NOTIFICATIONS_SECRET_TOKEN),
    );
}

/**
 * Mailer whose server refuses every message.
 */
function notificationsUseFailingMailer(): void
{
    Mail::extend('failing', fn (): AbstractTransport => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Connection to smtp://utente:segreto@mail.example.test:587 refused');
        }

        public function __toString(): string
        {
            return 'failing://';
        }
    });
    config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
    app('mail.manager')->forgetMailers();
}

/**
 * Messages delivered by the "array" mailer of the tests.
 *
 * @return list<RawMessage>
 */
function notificationsDelivered(): array
{
    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
    assert($transport instanceof ArrayTransport);

    return $transport->messages()->map(fn (SentMessage $sent): RawMessage => $sent->getOriginalMessage())->values()->all();
}

it('D-220: queues every e-mail on the "mail" queue, encrypted, with 5 tries of 60 s and growing waits', function () {
    Queue::fake();
    $user = User::factory()->create();

    app(Notifications::class)->send($user, notificationsLinkMail());

    Queue::assertPushedOn('mail', SendQueuedMailable::class, function (SendQueuedMailable $job): bool {
        return $job->mailable instanceof AccessLinkMail
            && $job->shouldBeEncrypted === true
            && $job->afterCommit === true
            && $job->tries === 5
            && $job->timeout === 60
            && $job->backoff() === [10, 30, 60, 120, 300];
    });
});

it('D-209: registers the queued e-mail with kind, recipient and subject, never the link', function () {
    Queue::fake();
    $user = User::factory()->create(['email' => 'mario.rossi@example.test']);

    $log = app(Notifications::class)->send($user, notificationsLinkMail());

    expect($log->fresh())
        ->user_id->toBe($user->id)
        ->type->toBe(NotificationType::Activation)
        ->recipient_email->toBe('mario.rossi@example.test')
        ->subject->toBe('Attiva il tuo account del Portale Commerciale Infront')
        ->status->toBe(NotificationStatus::Queued)
        ->attempts->toBe(0)
        ->sent_at->toBeNull();

    expect((string) json_encode(NotificationLog::query()->sole()->getAttributes()))->not->toContain(NOTIFICATIONS_SECRET_TOKEN);
});

it('D-209: marks the e-mail sent, with the message id of the mail server', function () {
    $user = User::factory()->create();

    $log = app(Notifications::class)->send($user, notificationsLinkMail());

    expect($log->fresh())
        ->status->toBe(NotificationStatus::Sent)
        ->attempts->toBe(1)
        ->sent_at->not->toBeNull()
        ->provider_message_id->not->toBeEmpty();
    expect(notificationsDelivered())->toHaveCount(1);
});

it('D-209: marks the e-mail failed after the last try, without addresses or credentials in the error', function () {
    notificationsUseFailingMailer();
    $user = User::factory()->create();

    // With the "sync" queue a failure is the last try: the job calls PortalMail::failed().
    expect(fn () => app(Notifications::class)->send($user, notificationsLinkMail()))
        ->toThrow(TransportException::class);

    $log = NotificationLog::query()->sole();
    expect($log)
        ->status->toBe(NotificationStatus::Failed)
        ->attempts->toBe(1)
        ->failed_at->not->toBeNull()
        ->sent_at->toBeNull()
        ->and($log->error)->toStartWith('TransportException: Connection to [url] refused')
        ->and($log->error)->not->toContain('segreto');
});

it('D-209: a message already accepted by the mail server stays sent', function () {
    $user = User::factory()->create();
    $mail = notificationsLinkMail();
    $log = app(Notifications::class)->send($user, $mail);

    $mail->failed(new RuntimeException('Late failure on '.url('attivazione/'.NOTIFICATIONS_SECRET_TOKEN)));

    expect($log->fresh()?->status)->toBe(NotificationStatus::Sent);
});

it('D-209: keeps only class and message of the error, cut to the column, without links', function () {
    $text = Notifications::errorText(new RuntimeException('Failure for '.url('nuova-password/'.NOTIFICATIONS_SECRET_TOKEN).' '.str_repeat('x', 2000)));

    expect($text)->toStartWith('RuntimeException: Failure for [url] ')
        ->and(mb_strlen($text))->toBeLessThanOrEqual(Notifications::ERROR_MAX_LENGTH)
        ->and($text)->not->toContain(NOTIFICATIONS_SECRET_TOKEN);
});

it('M01 §7: an Action rolled back queues no e-mail and leaves no row', function () {
    $user = User::factory()->create();

    expect(fn () => DB::transaction(function () use ($user): void {
        app(Notifications::class)->send($user, notificationsLinkMail());

        throw new RuntimeException('Action failed after the e-mail');
    }))->toThrow(RuntimeException::class);

    expect(NotificationLog::query()->count())->toBe(0)
        ->and(notificationsDelivered())->toBe([]);
});

it('M01 §7: the e-mail has the Infront header, a text version and no remote image', function () {
    $mail = notificationsLinkMail('Giulia');

    $mail->assertSeeInHtml('INFRONT')
        ->assertSeeInHtml('Portale Commerciale')
        ->assertSeeInHtml('Ciao Giulia,')
        ->assertSeeInHtml("Attiva l'account", false)
        ->assertSeeInHtml(url('attivazione/'.NOTIFICATIONS_SECRET_TOKEN), false)
        ->assertSeeInHtml('Messaggio automatico del Portale Commerciale Infront')
        ->assertDontSeeInHtml('<img', false)
        ->assertDontSeeInHtml('laravel.com', false)
        ->assertSeeInText('48 ore')
        ->assertSeeInText(url('attivazione/'.NOTIFICATIONS_SECRET_TOKEN));
});

it('M01 §7: the e-mail comes from the sender of the configuration', function () {
    config(['mail.from.address' => 'noreply@portale.example.test', 'mail.from.name' => 'Portale Commerciale Infront']);
    $user = User::factory()->create();

    app(Notifications::class)->send($user, notificationsLinkMail());

    $message = notificationsDelivered()[0] ?? null;
    assert($message instanceof Email);
    expect($message->getFrom()[0]->getAddress())->toBe('noreply@portale.example.test')
        ->and($message->getFrom()[0]->getName())->toBe('Portale Commerciale Infront')
        ->and($message->getAttachments())->toBe([]);
});

it('D-209: notifications:prune deletes the rows older than 12 months', function () {
    $user = User::factory()->create();
    Queue::fake();

    $this->travelTo(now()->subMonths(12)->subDay());
    app(Notifications::class)->send($user, notificationsLinkMail());
    $this->travelBack();
    $recent = app(Notifications::class)->send($user, notificationsLinkMail());

    $this->artisan('notifications:prune')
        ->expectsOutputToContain('1 righe più vecchie di 12 mesi cancellate')
        ->assertSuccessful();

    expect(NotificationLog::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('D-209: notifications:prune runs every Sunday at 4:00, Rome time, without overlapping', function () {
    /** @var ScheduledEvent|null $task */
    $task = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'notifications:prune'));

    expect($task)->not->toBeNull()
        ->and($task?->expression)->toBe('0 4 * * 0')
        ->and($task?->timezone)->toBe('Europe/Rome')
        ->and($task?->withoutOverlapping)->toBeTrue();
});

it('D-209: notifications:list shows the latest e-mails, read-only', function () {
    $user = User::factory()->create(['email' => 'lista.registro@example.test']);
    app(Notifications::class)->send($user, notificationsLinkMail());

    Artisan::call('notifications:list', ['--user' => 'lista.registro@example.test']);
    $output = Artisan::output();

    expect($output)->toContain('lista.registro@example.test')
        ->and($output)->toContain('Attivazione')
        ->and($output)->toContain('Inviata')
        ->and($output)->not->toContain(NOTIFICATIONS_SECRET_TOKEN);

    $this->artisan('notifications:list', ['--status' => 'unknown'])->assertFailed();
});

it('D-209: the database refuses an unknown status and a sent e-mail without its date', function () {
    $user = User::factory()->create();
    $row = [
        'user_id' => $user->id,
        'type' => 'activation',
        'recipient_email' => $user->email,
        'subject' => 'Prova',
        'queued_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    expect(fn () => DB::table('notification_logs')->insert([...$row, 'status' => 'lost']))->toThrow(QueryException::class);
    expect(fn () => DB::table('notification_logs')->insert([...$row, 'status' => 'sent']))->toThrow(QueryException::class);
    expect(fn () => DB::table('notification_logs')->insert([...$row, 'status' => 'failed']))->toThrow(QueryException::class);
});
