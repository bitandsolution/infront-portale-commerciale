<?php

namespace App\Enums;

/**
 * Keys of the system settings (D-191, D-286; PR-07), stored in settings.key.
 *
 * Each key carries its type, default, allowed range and whether the admin may change it from the
 * Settings page. The values are stable names (they are in the database and in the audit): an
 * existing value is never renamed. A new key needs the confirmation of Antonio (CLAUDE.md §2).
 *
 * Fixed keys stay readable in the page but cannot be changed:
 * - availability_method: only "seasonal" is implemented (D-062);
 * - led_step_seconds: invariant of D-063; another step would make the stored minutes invalid.
 */
enum SettingKey: string
{
    case AvailabilityMethod = 'availability_method';
    case LedStepSeconds = 'led_step_seconds';
    case HospitalityMaxSeats = 'hospitality_max_seats';
    case ForcedReasonMinLength = 'forced_reason_min_length';
    case AuditIpRetentionDays = 'audit_ip_retention_days';

    /** The audit events themselves last 5 years (RB-A2): keeping the IP longer is meaningless. */
    public const MAX_IP_RETENTION_DAYS = 1826;

    /**
     * Value used when the row is missing or holds a value outside the allowed range.
     */
    public function defaultValue(): int|string
    {
        return match ($this) {
            self::AvailabilityMethod => AvailabilityMethod::Seasonal->value,  // D-062
            self::LedStepSeconds => 30,                                       // D-063
            self::HospitalityMaxSeats => 6,                                   // D-147
            self::ForcedReasonMinLength => 10,                                // M04 §9
            self::AuditIpRetentionDays => self::configuredIpRetentionDays(),  // D-211, D-286
        };
    }

    public function isEditable(): bool
    {
        return match ($this) {
            self::AvailabilityMethod,
            self::LedStepSeconds => false,

            self::HospitalityMaxSeats,
            self::ForcedReasonMinLength,
            self::AuditIpRetentionDays => true,
        };
    }

    /**
     * Allowed integer range, or null for the keys holding a string.
     *
     * @return array{int, int}|null
     */
    public function range(): ?array
    {
        return match ($this) {
            self::AvailabilityMethod => null,
            self::LedStepSeconds => [30, 30],
            // Capped at 6 also by the CHECK on the seats of a proposal (progettazione-tecnica §5.3.3).
            self::HospitalityMaxSeats => [1, 6],
            self::ForcedReasonMinLength => [1, 200],
            self::AuditIpRetentionDays => [1, self::MAX_IP_RETENTION_DAYS],
        };
    }

    /**
     * Allowed strings, or null for the integer keys.
     *
     * @return list<string>|null
     */
    public function allowedStrings(): ?array
    {
        return match ($this) {
            self::AvailabilityMethod => array_map(
                fn (AvailabilityMethod $method): string => $method->value,
                AvailabilityMethod::cases(),
            ),
            default => null,
        };
    }

    public function accepts(mixed $value): bool
    {
        $range = $this->range();

        if ($range !== null) {
            return is_int($value) && $value >= $range[0] && $value <= $range[1];
        }

        return is_string($value) && in_array($value, $this->allowedStrings() ?? [], true);
    }

    /**
     * Section of the Settings page.
     */
    public function group(): string
    {
        return $this === self::AuditIpRetentionDays ? 'audit' : 'inventory';
    }

    /**
     * Italian label (lang/it/settings.php), also used as object label of the audit events.
     */
    public function label(): string
    {
        return $this->text('label');
    }

    public function help(): string
    {
        return $this->text('help');
    }

    /**
     * Why a fixed key cannot be changed; empty for the editable ones.
     */
    public function fixedNote(): string
    {
        return $this->isEditable() ? '' : $this->text('fixed');
    }

    /**
     * Unit shown next to the value; empty for the keys holding a string.
     */
    public function unit(): string
    {
        $unit = match ($this) {
            self::AvailabilityMethod => null,
            self::LedStepSeconds => 'seconds',
            self::HospitalityMaxSeats => 'seats',
            self::ForcedReasonMinLength => 'characters',
            self::AuditIpRetentionDays => 'days',
        };

        if ($unit === null) {
            return '';
        }

        $text = __('settings.units.'.$unit);

        return is_string($text) ? $text : $unit;
    }

    /**
     * @return list<self>
     */
    public static function editable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $key): bool => $key->isEditable()));
    }

    /**
     * Default of the IP retention: config/audit.php (AUDIT_IP_RETENTION_DAYS, 1826 days), kept in
     * the allowed range (D-286).
     */
    private static function configuredIpRetentionDays(): int
    {
        $days = config('audit.ip_retention_days');
        $days = is_numeric($days) ? (int) $days : self::MAX_IP_RETENTION_DAYS;

        return min(max($days, 1), self::MAX_IP_RETENTION_DAYS);
    }

    private function text(string $field): string
    {
        $text = __('settings.keys.'.$this->value.'.'.$field);

        return is_string($text) ? $text : $this->value;
    }
}
