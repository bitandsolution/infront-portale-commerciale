<?php

namespace App\Domain\Audit\Data;

use App\Enums\CatalogValueKind;
use App\Enums\SettingKey;
use App\Models\BenefitCategory;
use App\Models\Club;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\OnTopAsset;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\Setting;
use App\Models\Stadium;
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
     * A club (F1-02), e.g. "SS Lazio (LAZIO)": also the subject of its logo and alias events.
     */
    public static function club(Club $club): self
    {
        return new self('club', $club->id, $club->name.' ('.$club->code.')');
    }

    /**
     * A match (F1-03), e.g. "Alfa – Beta, 7ª giornata, 18/10/2027".
     */
    public static function match(FixtureMatch $match): self
    {
        return new self('match', $match->id, $match->label());
    }

    public static function stadium(Stadium $stadium): self
    {
        return new self('stadium', $stadium->id, $stadium->name.' ('.$stadium->city.')');
    }

    /**
     * A value of the catalogue (F2-01), e.g. "Domination (DOMINATION)": the type is the kind
     * (led_format, partnership_tier, hospitality_variant, benefit_category).
     */
    public static function catalogValue(LedFormat|PartnershipTier|HospitalityVariant|BenefitCategory $value): self
    {
        return new self(CatalogValueKind::of($value)->value, $value->id, $value->name.' ('.$value->code.')');
    }

    /**
     * The price list of a season (F2-02), e.g. "Listino 2027-28". A price adds club and format to
     * the label, e.g. "Listino 2027-28 · SS Lazio (LAZIO) · 1ª fila (FILA_1)": the events of all the
     * prices of a list share the same object.
     */
    public static function priceList(PriceList $priceList, ?string $detail = null): self
    {
        $label = __('catalog.price_list_label', ['season' => $priceList->season->code]);
        $label = is_string($label) ? $label : $priceList->season->code;

        return new self('price_list', $priceList->id, $detail === null ? $label : $label.' · '.$detail);
    }

    /**
     * An on-top text of a price list (F2-03), e.g. "Listino 2027-28 · SS Lazio (LAZIO) · Match
     * sponsor"; the general texts say "Generale" instead of the club.
     */
    public static function onTopAsset(PriceList $priceList, OnTopAsset $asset, ?Club $club): self
    {
        $general = __('catalog.on_top_general');
        $owner = $club === null ? (is_string($general) ? $general : 'Generale') : $club->name.' ('.$club->code.')';
        $subject = self::priceList($priceList, $owner.' · '.$asset->title);

        return new self('on_top_asset', $asset->id, $subject->label);
    }

    /**
     * A capacity of the inventory (F2-04), e.g. "SS Lazio (LAZIO) · 2027-28 · 1ª fila (FILA_1)".
     */
    public static function capacity(int $id, Season $season, Club $club, string $resource): self
    {
        return new self('capacity', $id, $club->name.' ('.$club->code.') · '.$season->code.' · '.$resource);
    }

    /**
     * Login attempted with an e-mail that matches no user: only what was typed (M10-CA2).
     */
    public static function attemptedEmail(string $email): self
    {
        return new self('user', null, mb_substr($email, 0, 255));
    }
}
