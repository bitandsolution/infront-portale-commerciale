<?php

namespace App\Enums;

/**
 * Action of an audit event (M10 §4.1; D-127, D-128), stored in audit_events.action (C8).
 *
 * The values are stable names: they are what the audit page and report R-07 (slice F8) filter on,
 * so an existing value is never renamed. The area is part of the action.
 */
enum AuditAction: string
{
    // Access (D-128, D-274): with the IP address (M10 §4.2).
    case LoginSucceeded = 'access.login_succeeded';
    case LoginFailed = 'access.login_failed';
    case Logout = 'access.logout';
    case LockedOut = 'access.locked_out';
    case TwoFactorFailed = 'access.two_factor_failed';
    case RecoveryCodeUsed = 'access.recovery_code_used';
    case SessionExpired = 'access.session_expired';
    case SessionClosed = 'access.session_closed';
    // Self-service reset from the login page (PR-08): every request, with the reason when no link
    // was sent (unknown address, deactivated, not activated, no second factor, too many requests).
    case PasswordResetRequested = 'access.password_reset_requested';

    // Users (M01 §5): the names announced in the comments of App\Domain\Users\Actions.
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserRoleChanged = 'user.role_changed';
    case UserDeactivated = 'user.deactivated';
    case UserReactivated = 'user.reactivated';
    case UserAccessLinkSent = 'user.access_link_sent';
    case UserPasswordResetRequested = 'user.password_reset_requested';
    case UserPasswordReset = 'user.password_reset';
    case UserPasswordChanged = 'user.password_changed';
    case UserProfileUpdated = 'user.profile_updated';
    case UserTwoFactorReset = 'user.two_factor_reset';
    case UserTwoFactorConfigured = 'user.two_factor_configured';
    case UserActivated = 'user.activated';

    // Settings (PR-07): one event per changed key, with the value before and after.
    case SettingUpdated = 'setting.updated';

    // Sports registry (M02; F1-01, D-321): one event per manual write, with the changed fields.
    case SeasonCreated = 'season.created';
    case SeasonUpdated = 'season.updated';
    case CompetitionCreated = 'competition.created';
    case CompetitionUpdated = 'competition.updated';
    case EditionCreated = 'edition.created';
    case EditionUpdated = 'edition.updated';
    case EditionRemoved = 'edition.removed';
    // F1-02: stadiums, clubs, their logos and aliases.
    case StadiumCreated = 'stadium.created';
    case StadiumUpdated = 'stadium.updated';
    case ClubCreated = 'club.created';
    case ClubUpdated = 'club.updated';
    case ClubLogoChanged = 'club.logo_changed';
    case ClubLegacyImported = 'club.legacy_imported';
    case ClubAliasCreated = 'club_alias.created';
    case ClubAliasRemoved = 'club_alias.removed';
    // F1-03: enrollments and matches.
    case EnrollmentCreated = 'enrollment.created';
    case EnrollmentRemoved = 'enrollment.removed';
    case MatchCreated = 'match.created';
    case MatchUpdated = 'match.updated';
    case MatchCancelled = 'match.cancelled';
    case MatchRestored = 'match.restored';

    // F1-04: one event for the whole calendar import (D-321).
    case CalendarImportConfirmed = 'calendar_import.confirmed';

    // F2-01: values of the catalogue changed by the admin (D-331); codes never change.
    case LedFormatUpdated = 'led_format.updated';
    case PartnershipTierUpdated = 'partnership_tier.updated';
    case HospitalityVariantUpdated = 'hospitality_variant.updated';
    case BenefitCategoryUpdated = 'benefit_category.updated';

    // F2-02: price list of a season and LED prices (D-333): one event for every price changed.
    case PriceListCreated = 'price_list.created';
    case PriceListPublished = 'price_list.published';
    case LedPriceUpdated = 'led_price.updated';

    // F2-03: partnership and hospitality prices, benefit texts and on-top texts (D-335).
    case PartnershipPriceUpdated = 'partnership_price.updated';
    case HospitalityPriceUpdated = 'hospitality_price.updated';
    case PartnershipBenefitUpdated = 'partnership_benefit.updated';
    case OnTopAssetCreated = 'on_top_asset.created';
    case OnTopAssetUpdated = 'on_top_asset.updated';
    case OnTopAssetRemoved = 'on_top_asset.removed';

    // F2-04: capacities of the inventory per season and club (D-191, D-337): area "inventory".
    case PoolCapacityUpdated = 'pool_capacity.updated';
    case PartnershipCapacityUpdated = 'partnership_capacity.updated';
    case HospitalityCapacityUpdated = 'hospitality_capacity.updated';

    // F3-01: commodity sectors of the clients (D-357): area "clients".
    case CommoditySectorCreated = 'commodity_sector.created';
    case CommoditySectorUpdated = 'commodity_sector.updated';

    // F3-02: clients (D-356): area "clients".
    case ClientCreated = 'client.created';
    case ClientUpdated = 'client.updated';

    // F3-03: reassignment, deactivation and merge of clients (D-082, D-359): area "clients".
    case ClientReassigned = 'client.reassigned';
    case ClientDeactivated = 'client.deactivated';
    case ClientReactivated = 'client.reactivated';
    case ClientMerged = 'client.merged';

    // F4-02: drafts of the configurator (D-372): area "proposals". The automatic save writes one
    // event with the changed fields only; F4-05 adds the duplication.
    case ProposalDraftCreated = 'proposal.draft_created';
    case ProposalDraftUpdated = 'proposal.draft_updated';
    case ProposalDraftDeleted = 'proposal.draft_deleted';
    case ProposalDraftDuplicated = 'proposal.draft_duplicated';
    // DM-01: a new variant of a group of proposals (D-396).
    case ProposalVariantAdded = 'proposal.variant_added';
    // DM-03a: sending and rejection of a group (D-395, D-412), one event per variant.
    case ProposalSubmitted = 'proposal.submitted';
    case ProposalRejected = 'proposal.rejected';
    case ProposalApproved = 'proposal.approved';
    case ProposalClientChosen = 'proposal.client_chosen';

    // DM-02: slide library (D-406): area "content".
    case SlideCreated = 'slide.created';
    case SlideUpdated = 'slide.updated';

    public function area(): AuditArea
    {
        return match (strstr($this->value, '.', true)) {
            'access' => AuditArea::Access,
            'setting' => AuditArea::Settings,
            'season', 'competition', 'edition', 'stadium', 'club', 'club_alias', 'enrollment', 'match', 'calendar_import' => AuditArea::Sports,
            'led_format', 'partnership_tier', 'hospitality_variant', 'benefit_category', 'price_list', 'led_price', 'partnership_price', 'hospitality_price', 'partnership_benefit', 'on_top_asset' => AuditArea::Catalog,
            'pool_capacity', 'partnership_capacity', 'hospitality_capacity' => AuditArea::Inventory,
            'commodity_sector', 'client' => AuditArea::Clients,
            'proposal' => AuditArea::Proposals,
            'slide' => AuditArea::Content,
            default => AuditArea::Users,
        };
    }

    /**
     * Access events record the IP address of the request (M10 §4.2), the others never do.
     */
    public function recordsIpAddress(): bool
    {
        return $this->area() === AuditArea::Access;
    }

    /**
     * Italian label (lang/it/audit.php).
     */
    public function label(): string
    {
        $label = __('audit.actions.'.str_replace('.', '_', $this->value));

        return is_string($label) ? $label : $this->value;
    }
}
