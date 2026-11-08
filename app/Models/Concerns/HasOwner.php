<?php

namespace App\Models\Concerns;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Owner scope for the agent (RB-03, D-076, D-215): second line of defence after the Policies.
 *
 * For models owned by an agent (clients, proposals, documents from the next slices):
 * `Client::query()->visibleTo($user)` returns only the agent's own rows, all rows for
 * manager and admin, and nothing for a deactivated user. A mistake in a controller cannot expose
 * the data of another agent.
 *
 * The owner column is `owner_id` (clients); a model with another column declares its own
 * ownerColumn(): the proposals keep `agent_id` of the data model (D-376).
 *
 * @phpstan-require-extends Model
 */
trait HasOwner
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, $this->ownerColumn());
    }

    /**
     * Column with the id of the owning agent.
     */
    public function ownerColumn(): string
    {
        return 'owner_id';
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->is_active === false) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->role === Role::Agent) {
            $query->where($this->qualifyColumn($this->ownerColumn()), $user->id);
        }
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->getAttribute($this->ownerColumn()) === $user->id;
    }
}
