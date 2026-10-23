<?php

namespace App\Enums;

/**
 * Fixed user roles (D-208). Stored as VARCHAR(32) on users from PR-02 (C8).
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Agent = 'agent';

    /**
     * Italian label for the interface (lang/it/ui.php).
     */
    public function label(): string
    {
        $label = __('ui.roles.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }

    /**
     * Admin and manager share the back-office navigation (ux-architettura, "Navigazione per ruolo").
     */
    public function isBackOffice(): bool
    {
        return $this !== self::Agent;
    }

    /**
     * Session expiry after inactivity (M01 §6, D-214): 8 hours for the agent, who works on
     * proposals during the day; 2 hours for admin and manager. Applied by EnforceIdleTimeout.
     */
    public function idleTimeoutMinutes(): int
    {
        return match ($this) {
            self::Agent => 480,
            self::Admin, self::Manager => 120,
        };
    }
}
