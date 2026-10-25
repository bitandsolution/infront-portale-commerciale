<?php

use App\Domain\Operations\Services\HealthCheck;
use App\Domain\Operations\Services\SchedulerHeartbeat;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

/*
| Internal health (progettazione-tecnica §5.2.7; D-179; PR-09): public /health with 200 "ok" or
| 503 "fail" and no details, the same checks with their details from "php artisan health:check".
|
| Every test starts from a healthy portal: Horizon running (the master repository is replaced, no
| worker runs in the tests), a fresh scheduler heartbeat, queues of their own in Redis (never the
| real documents, mail and default, which a worker on the VM would empty) and no disk limit.
*/

beforeEach(function () {
    healthHorizonStatus('running');
    app(SchedulerHeartbeat::class)->beat();
    config([
        'health.queues' => ['health-test-'.bin2hex(random_bytes(4))],
        'health.max_disk_used_percent' => 100,
    ]);
});

afterEach(function () {
    /** @var RedisQueue $queue */
    $queue = Queue::connection(HealthCheck::QUEUE_CONNECTION);
    foreach (config('health.queues') as $name) {
        $queue->getConnection()->del($queue->getQueue($name), $queue->getQueue($name).':notify');
    }
});

/**
 * Replaces the masters of Horizon: 'running', 'paused' or null (not started).
 */
function healthHorizonStatus(?string $status): void
{
    test()->mock(MasterSupervisorRepository::class, function ($mock) use ($status): void {
        $mock->shouldReceive('all')->andReturn($status === null ? [] : [(object) ['name' => 'master', 'status' => $status]]);
    });
}

/**
 * Puts a job pushed $seconds ago at the head of the test queue.
 */
function healthQueueJob(int $seconds): void
{
    /** @var RedisQueue $queue */
    $queue = Queue::connection(HealthCheck::QUEUE_CONNECTION);
    $queue->pushRaw((string) json_encode([
        'uuid' => 'health-test',
        'displayName' => 'HealthTestJob',
        'pushedAt' => (string) (now()->getTimestamp() - $seconds),
    ]), config('health.queues')[0]);
}

/**
 * @return array<string, bool>
 */
function healthOutcome(): array
{
    $outcome = [];
    foreach (app(HealthCheck::class)->run() as $result) {
        $outcome[$result->check] = $result->ok;
    }

    return $outcome;
}

it('D-179: /health answers 200 "ok" when every check passes', function () {
    $response = $this->get('/health');

    $response->assertOk()->assertExactJson(['status' => 'ok']);
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');

    expect(healthOutcome())->toBe([
        'database' => true, 'redis' => true, 'horizon' => true, 'queues' => true, 'disk' => true, 'scheduler' => true,
    ]);
});

it('D-179: /health answers 503 "fail" without details when a check fails', function () {
    healthHorizonStatus(null);

    $response = $this->get('/health');

    $response->assertStatus(503)->assertExactJson(['status' => 'fail']);
    expect($response->getContent())->not->toContain('horizon');
});

it('D-179: /health opens no session and sets no cookie', function () {
    $response = $this->get('/health');

    expect($response->headers->getCookies())->toBe([]);
});

it('PR-09: Laravel\'s /up no longer exists', function () {
    $this->get('/up')->assertNotFound();
});

it('§5.2.7: Horizon paused or not started fails the check', function (?string $status) {
    healthHorizonStatus($status);

    expect(healthOutcome()['horizon'])->toBeFalse();
})->with(['paused' => 'paused', 'not started' => null]);

it('§5.2.7: a job waiting more than 10 minutes fails the queues check', function () {
    healthQueueJob(540);
    expect(healthOutcome()['queues'])->toBeTrue();

    $this->travel(2)->minutes();
    expect(healthOutcome()['queues'])->toBeFalse();
});

it('§5.2.7: the disk used beyond the limit fails the check', function () {
    config(['health.max_disk_used_percent' => 0]);

    expect(healthOutcome()['disk'])->toBeFalse();
});

it('§5.2.7: a scheduler silent for more than 10 minutes fails the check', function () {
    $this->travel(9)->minutes();
    expect(healthOutcome()['scheduler'])->toBeTrue();

    $this->travel(2)->minutes();
    expect(healthOutcome()['scheduler'])->toBeFalse();
});

it('§5.2.7: a database that cannot be reached fails only its check, without the error message', function () {
    config([
        'database.connections.health_broken' => array_merge(config('database.connections.mariadb'), [
            'host' => '127.0.0.1',
            'port' => 1,
            'password' => 'segreto-di-prova',
        ]),
        'database.default' => 'health_broken',
    ]);

    try {
        $results = app(HealthCheck::class)->run();
    } finally {
        config(['database.default' => 'mariadb']);
        DB::purge('health_broken');
    }

    expect($results[0]->check)->toBe('database')
        ->and($results[0]->ok)->toBeFalse()
        ->and($results[0]->detail)->not->toContain('segreto-di-prova')
        ->and(collect($results)->where('ok', false)->count())->toBe(1);
});

it('PR-09: health:check prints every check and exits with 0 when healthy', function () {
    $exit = Artisan::call('health:check');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('[OK] Database: raggiungibile')
        ->and($output)->toContain('[OK] Code (Horizon): in esecuzione')
        ->and($output)->toContain('[OK] Scheduler: ultimo segnale')
        ->and($output)->toContain('Stato: tutto funziona.');
});

it('PR-09: health:check exits with 1 and names the failing checks', function () {
    healthHorizonStatus('paused');
    config(['health.max_disk_used_percent' => 0]);

    $exit = Artisan::call('health:check');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('[KO] Code (Horizon): in pausa')
        ->and($output)->toContain('[KO] Disco: occupato')
        ->and($output)->toContain('Stato: 2 controlli non superati.');
});

it('PR-09: the scheduler writes the heartbeat every minute', function () {
    $this->travel(1)->hours();
    expect(healthOutcome()['scheduler'])->toBeFalse();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event instanceof CallbackEvent && $event->description === 'operations:scheduler-heartbeat');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');

    $event->run(app());

    expect(healthOutcome()['scheduler'])->toBeTrue();
});
