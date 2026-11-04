<?php

namespace App\Domain\Clients\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Writes on the clients registry that need the duplicate check (M05 RB-02): the check on the
 * normalized company name is in the code, not a UNIQUE constraint (D-196, D-197), so two creations
 * at the same time could both pass it. Every such write runs under one short lock of the cache
 * (Redis in production) and in its own transaction, committed before the lock is released.
 */
final class ClientRegistry
{
    private const LOCK = 'clients:registry';

    private const LOCK_SECONDS = 10;

    private const WAIT_SECONDS = 5;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $write
     * @return TResult
     */
    public function write(Closure $write): mixed
    {
        return Cache::lock(self::LOCK, self::LOCK_SECONDS)
            ->block(self::WAIT_SECONDS, fn (): mixed => DB::transaction($write));
    }
}
