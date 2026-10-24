<?php

namespace App\Models\Builders;

use App\Domain\Audit\Exceptions\AuditEventImmutable;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder of the audit events: reading and inserting only (RB-A1, D-210). Every mass update
 * or delete through Eloquent throws before reaching the database, where the triggers would refuse
 * it anyway.
 *
 * @template TModel of AuditEvent
 *
 * @extends Builder<TModel>
 */
class AuditEventBuilder extends Builder
{
    /**
     * @param  array<array-key, mixed>  $values
     * @return int
     */
    public function update(array $values)
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, string>|string  $uniqueBy
     * @param  array<array-key, mixed>|null  $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @param  array<array-key, string>|string|null  $column
     * @return int|false
     */
    public function touch($column = null)
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     * @return int
     */
    public function incrementEach(array $columns, array $extra = [])
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     * @return int
     */
    public function decrementEach(array $columns, array $extra = [])
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @return mixed
     */
    public function delete()
    {
        throw AuditEventImmutable::make();
    }

    /**
     * @return mixed
     */
    public function forceDelete()
    {
        throw AuditEventImmutable::make();
    }
}
