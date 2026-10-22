<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Portal user (M01; D-208, D-209).
 *
 * One fixed role per user (RB-01); users are deactivated, never deleted (RB-05).
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property Role $role
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 * @property int|null $deactivated_by
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'role', 'password', 'is_active', 'activated_at', 'deactivated_at', 'deactivated_by'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * E-mail addresses are stored in lower case (the unique index is also case-insensitive, C1).
     */
    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    public function isAgent(): bool
    {
        return $this->hasRole(Role::Agent);
    }
}
