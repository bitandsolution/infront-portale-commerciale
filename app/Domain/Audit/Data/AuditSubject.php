<?php

namespace App\Domain\Audit\Data;

use App\Enums\SettingKey;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Season;
use App\Models\Setting;
use App\Models\User;

/**
 * Object an audit event talks about (M10 §4.2: type and identifier, e.g. "proposal n. 192").
 *
 * The type is a short stable name, not the class name, so a renamed class never breaks the trail.
 * The label is a readable description that stays when the object changes or disappears (M10 §8).
 */
final readonly class AuditSubject
{
    public function __construct(
        public string $type,
        public ?int $id,
        public ?string $label,
    ) {}

    public static function user(User $user): self
    {
        return new self('user', $user->id, $user->fullName().' ('.$user->email.')');
    }

    /**
     * A system setting (PR-07): the label carries the stable key, so the event stays readable if
     * the Italian label changes.
     */
    public static function setting(SettingKey $key, Setting $setting): self
    {
        return new self('setting', $setting->id, $key->label().' ('.$key->value.')');
    }

    /**
     * A season of the sports registry (F1-01), e.g. "Stagione 2027-28 (2027-28)".
     */
    public static function season(Season $season): self
    {
        return new self('season', $season->id, $season->name.' ('.$season->code.')');
    }

    public static function competition(Competition $competition): self
    {
        return new self('competition', $competition->id, $competition->name.' ('.$competition->code.')');
    }

    /**
     * An edition (competition × season), e.g. "Serie A 2027-28". The label is computed before a
     * removal, so the event stays readable afterwards.
     */
    public static function edition(CompetitionSeason $edition): self
    {
        return new self('competition_season', $edition->id, $edition->label());
    }

    /**
     * Login attempted with an e-mail that matches no user: only what was typed (M10-CA2).
     */
    public static function attemptedEmail(string $email): self
    {
        return new self('user', null, mb_substr($email, 0, 255));
    }
}
