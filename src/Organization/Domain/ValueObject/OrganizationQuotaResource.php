<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use function number_format;
use function sprintf;

/**
 * Enum OrganizationQuotaResource.
 *
 * Enumerates the countable resources whose quantity a subscription plan may
 * cap. Each case maps to a resource an organization accumulates and whose
 * creation is blocked once the plan limit is reached.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationQuotaResource: string
{
  case MEMBERS = 'members';
  case FACILITIES = 'facilities';
  case EQUIPMENT = 'equipment';
  case INSPECTIONS = 'inspections';
  // #region Methods

  /**
   * Method label.
   *
   * Returns a human-readable label for the resource.
   *
   * @since 1.0.0
   *
   * @return string the resource label
   */
  public function label(): string
  {
    return match ($this) {
      self::MEMBERS => 'Members',
      self::FACILITIES => 'Facilities',
      self::EQUIPMENT => 'Equipment',
      self::INSPECTIONS => 'Inspections',
    };
  }

  /**
   * Method noun.
   *
   * Returns the lower-case plural noun used to phrase a quota sentence (for
   * example "facilities" or "equipment items").
   *
   * @since 1.0.0
   *
   * @return string the resource noun
   */
  public function noun(): string
  {
    return match ($this) {
      self::MEMBERS => 'team members',
      self::FACILITIES => 'facilities',
      self::EQUIPMENT => 'equipment items',
      self::INSPECTIONS => 'inspections',
    };
  }

  /**
   * Method summarize.
   *
   * Phrases the plan allowance for this resource as a human sentence, for
   * example "Up to 125 facilities" or "Unlimited inspections" when there is no
   * cap.
   *
   * @since 1.0.0
   *
   * @param ?int $limit the per-resource cap, or null when unlimited
   *
   * @return string the quota sentence
   */
  public function summarize(?int $limit): string
  {
    if (null === $limit) {
      return sprintf('Unlimited %s', $this->noun());
    }

    return sprintf('Up to %s %s', number_format($limit), $this->noun());
  }
  // #endregion
}
