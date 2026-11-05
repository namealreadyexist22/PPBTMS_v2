<?php

namespace App\Enums;

/**
 * The steps of a competitive bidding, numbered as in the agency's process: 1 is the PR created and 2 the
 * BAC receiving it (both the PR's, see PR Receiving). Steps 3–8 are the bidding's; 9–15 go lot by lot
 * (each lot is awarded on its own). Each one also goes on the PRs' status trail.
 */
enum BiddingStep: string
{
    case PreprocSet = 'preproc_set';
    case PreprocHeld = 'preproc_held';
    case Advertised = 'advertised';
    case PrebidHeld = 'prebid_held';
    case Supplemental = 'supplemental';
    case Bidding = 'bidding';
    case PostQualified = 'post_qualified';
    case BacResolution = 'bac_resolution';
    case Noa = 'noa';
    case Po = 'po';
    case Ntp = 'ntp';
    case ForDelivery = 'for_delivery';
    case Delivered = 'delivered';

    public function number(): int
    {
        return match ($this) {
            self::PreprocSet    => 3,
            self::PreprocHeld   => 4,
            self::Advertised    => 5,
            self::PrebidHeld    => 6,
            self::Supplemental  => 7,
            self::Bidding       => 8,
            self::PostQualified => 9,
            self::BacResolution => 10,
            self::Noa           => 11,
            self::Po            => 12,
            self::Ntp           => 13,
            self::ForDelivery   => 14,
            self::Delivered     => 15,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PreprocSet    => 'Set for Pre-Procurement Conference',
            self::PreprocHeld   => 'Pre-Procurement Conference held',
            self::Advertised    => 'Advertisement posted',
            self::PrebidHeld    => 'Pre-bid Conference held',
            self::Supplemental  => 'Supplemental bid bulletin',
            self::Bidding       => 'Public Bidding held',
            self::PostQualified => 'Post-qualification done',
            self::BacResolution => 'BAC Resolution received',
            self::Noa           => 'Notice of Award received',
            self::Po            => 'Purchase Order received',
            self::Ntp           => 'Notice to Proceed received',
            self::ForDelivery   => 'For delivery',
            self::Delivered     => 'Delivered',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PreprocSet, self::PreprocHeld => 'fas fa-users',
            self::Advertised    => 'fas fa-bullhorn',
            self::PrebidHeld    => 'fas fa-comments',
            self::Supplemental  => 'fas fa-file-alt',
            self::Bidding       => 'fas fa-gavel',
            self::PostQualified => 'fas fa-search',
            self::BacResolution => 'fas fa-file-signature',
            self::Noa, self::Ntp => 'fas fa-envelope-open-text',
            self::Po            => 'fas fa-file-invoice',
            self::ForDelivery   => 'fas fa-truck',
            self::Delivered     => 'fas fa-check-circle',
        };
    }

    /** Steps 9–15 are recorded lot by lot (after the bids are evaluated). */
    public function isPerLot(): bool
    {
        return $this->number() >= 9;
    }

    /** May be skipped (e.g. no pre-procurement conference for a small ABC, no supplemental bulletin). */
    public function isOptional(): bool
    {
        return in_array($this, [self::PreprocSet, self::PreprocHeld, self::PrebidHeld, self::Supplemental], true);
    }
}
