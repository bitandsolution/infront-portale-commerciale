<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One e-mail sent by the portal (M01 §10; D-209; PR-08). Written only by
 * App\Domain\Notifications\Services\Notifications and by the delivery events; never the content of
 * the message, never a link or a token.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property string $recipient_email
 * @property string $subject
 * @property NotificationStatus $status
 * @property int $attempts
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property Carbon $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
final class NotificationLog extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
