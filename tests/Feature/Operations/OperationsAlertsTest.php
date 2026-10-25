<?php

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Mail\JobFailedMail;
use App\Mail\QueueStalledMail;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Mail;
use Laravel\Horizon\Events\LongWaitDetected;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/*
| Alerts of the queues (progettazione-tecnica §5.2.7, §5.5.1; D-298; PR-09): a job failed after its
| last try, a queue waiting longer than 10 minutes. E-mail to every active admin, sent at once (not
| through the queue, which may be the stalled one), registered in notification_logs, at most one an
| hour for the same job or queue.
*/

function alertsJobFailed(string $connection = 'redis', string $name = 'App\Jobs\GenerateDocument', string $queue = 'documents'): void
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn($name);
    $job->shouldReceive('getQueue')->andReturn($queue);
    // Read by Horizon's own listener of the same event.
    $job->shouldReceive('getJobId')->andReturn('alerts-test-job');

    event(new JobFailed($connection, $job, new RuntimeException('Chromium | timeout after 300 s for mario.rossi@example.test, see https://x.test/?token=abc')));
}

/**
 * @return list<array{0: NotificationType, 1: int}>
 */
function alertsLogs(): array
{
    return NotificationLog::query()->orderBy('id')->get()
        ->map(fn (NotificationLog $log): array => [$log->type, $log->user_id])->all();
}

it('D-298: a job failed after its last try sends an e-mail at once to every active admin', function () {
    Mail::fake();
    $anna = User::factory()->admin()->create(['first_name' => 'Anna']);
    $bruno = User::factory()->admin()->create(['first_name' => 'Bruno']);
    User::factory()->admin()->inactive()->create();
    User::factory()->manager()->create();
    User::factory()->agent()->create();

    alertsJobFailed();

    Mail::assertSent(JobFailedMail::class, function (JobFailedMail $mail) use ($anna): bool {
        if (! $mail->hasTo($anna->email)) {
            return false;
        }
        $mail->assertHasSubject('Lavoro non riuscito nella coda documents');
        $mail->assertSeeInHtml('Ciao Anna,');
        $mail->assertSeeInHtml('App\Jobs\GenerateDocument', false);
        $mail->assertSeeInHtml('Chromium / timeout after 300 s', false);
        $mail->assertDontSeeInHtml('token=abc', false);
        $mail->assertSeeInHtml(url('horizon/failed'), false);

        return true;
    });
    Mail::assertSentCount(2);
    Mail::assertNothingQueued();

    expect(alertsLogs())->toBe([[NotificationType::JobFailed, $anna->id], [NotificationType::JobFailed, $bruno->id]]);
});

it('D-298: at most one e-mail an hour for the same kind of job', function () {
    Mail::fake();
    User::factory()->admin()->create();

    alertsJobFailed();
    alertsJobFailed();
    Mail::assertSentCount(1);

    alertsJobFailed(name: 'App\Mail\LoginLockedMail', queue: 'mail');
    Mail::assertSentCount(2);

    $this->travel(61)->minutes();
    alertsJobFailed();
    Mail::assertSentCount(3);
});

it('D-298: jobs run synchronously send no alert (their request already reports the error)', function () {
    Mail::fake();
    User::factory()->admin()->create();

    alertsJobFailed(connection: 'sync');

    Mail::assertNothingOutgoing();
});

it('D-298: a queue waiting longer than 10 minutes sends an e-mail at once to every active admin', function () {
    Mail::fake();
    $anna = User::factory()->admin()->create(['first_name' => 'Anna']);

    event(new LongWaitDetected('redis', 'mail', 754));

    Mail::assertSent(QueueStalledMail::class, function (QueueStalledMail $mail) use ($anna): bool {
        $mail->assertHasSubject('Coda mail ferma da oltre 10 minuti');
        $mail->assertSeeInHtml('Ciao Anna,');
        $mail->assertSeeInHtml('circa 13 minuti');
        $mail->assertSeeInHtml(url('horizon/dashboard'), false);

        return $mail->hasTo($anna->email);
    });
    Mail::assertNothingQueued();
    expect(alertsLogs())->toBe([[NotificationType::QueueStalled, $anna->id]]);
});

it('D-298: at most one e-mail an hour for the same queue', function () {
    Mail::fake();
    User::factory()->admin()->create();

    event(new LongWaitDetected('redis', 'mail', 700));
    event(new LongWaitDetected('redis', 'mail', 900));
    event(new LongWaitDetected('redis', 'documents', 700));
    Mail::assertSentCount(2);

    $this->travel(61)->minutes();
    event(new LongWaitDetected('redis', 'mail', 700));
    Mail::assertSentCount(3);
});

it('D-209: the alert is registered as sent when the mail server accepts it', function () {
    User::factory()->admin()->create();

    event(new LongWaitDetected('redis', 'default', 700));

    $log = NotificationLog::query()->sole();
    expect($log->status)->toBe(NotificationStatus::Sent)
        ->and($log->attempts)->toBe(1)
        ->and($log->sent_at)->not->toBeNull();
});

it('D-209: an alert refused by the mail server is registered as not sent and breaks nothing', function () {
    Mail::extend('alerts-failing', fn (): AbstractTransport => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Connection refused');
        }

        public function __toString(): string
        {
            return 'alerts-failing://';
        }
    });
    config(['mail.mailers.alerts-failing' => ['transport' => 'alerts-failing'], 'mail.default' => 'alerts-failing']);
    app('mail.manager')->forgetMailers();
    User::factory()->admin()->create();

    alertsJobFailed();

    $log = NotificationLog::query()->sole();
    expect($log->status)->toBe(NotificationStatus::Failed)
        ->and($log->error)->toContain('Connection refused');
});
