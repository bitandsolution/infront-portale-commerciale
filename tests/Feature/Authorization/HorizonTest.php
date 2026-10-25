<?php

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;

/*
| Queues and Horizon panel (progettazione-tecnica §5.2.7, §5.5.1; D-220, D-222; PR-08). The routes
| of the panel are in the route × role matrix (RouteMatrixTest); here the configuration of the
| queues and Horizon's own check, which by default lets everybody in on a "local" environment.
*/

function horizonCheckFor(?User $user): bool
{
    $request = Request::create('/horizon');
    $request->setUserResolver(fn (): ?User => $user);

    return Horizon::check($request);
}

it('D-215: the panel is for the admin only, also on the development VM ("local")', function () {
    app()->detectEnvironment(fn (): string => 'local');

    expect(horizonCheckFor(null))->toBeFalse()
        ->and(horizonCheckFor(User::factory()->agent()->make()))->toBeFalse()
        ->and(horizonCheckFor(User::factory()->manager()->make()))->toBeFalse()
        ->and(horizonCheckFor(User::factory()->admin()->inactive()->make()))->toBeFalse()
        ->and(horizonCheckFor(User::factory()->admin()->make()))->toBeTrue();
});

it('D-220: three queues with the workers, tries and timeouts of progettazione-tecnica §5.5.1', function () {
    $supervisors = config('horizon.environments.production');

    expect(collect($supervisors)->map(fn (array $supervisor): array => [
        $supervisor['queue'],
        $supervisor['balance'],
        $supervisor['minProcesses'],
        $supervisor['maxProcesses'],
        $supervisor['tries'],
        $supervisor['timeout'],
    ])->all())->toBe([
        'supervisor-documents' => [['documents'], 'off', 2, 2, 3, 300],
        'supervisor-mail' => [['mail'], 'off', 2, 2, 5, 60],
        'supervisor-default' => [['default'], 'off', 1, 1, 3, 120],
    ]);

    // The same supervisors on the VM and on staging.
    expect(config('horizon.environments.local'))->toBe($supervisors)
        ->and(config('horizon.environments.staging'))->toBe($supervisors);
});

it('D-222: at most 2 documents in parallel, 2 workers always ready', function () {
    expect(config('horizon.environments.production.supervisor-documents.maxProcesses'))->toBe(2)
        ->and(config('horizon.environments.production.supervisor-documents.minProcesses'))->toBe(2)
        ->and(config('horizon.environments.production.supervisor-documents.balance'))->toBe('off');
});

it('D-220: a job still running is never picked up a second time (retry_after above every timeout)', function () {
    $longest = collect(config('horizon.environments.production'))->max('timeout');

    expect(config('queue.connections.redis.retry_after'))->toBeGreaterThan($longest);
});
