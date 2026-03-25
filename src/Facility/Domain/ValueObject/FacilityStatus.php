<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * Enum FacilityStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum FacilityStatus: string
{
  case ACTIVE = 'active';
  case ARCHIVED = 'archived';

  // #region Methods
  /**
   * Method isActive.
   *
   * @since 1.0.0
   */
  public function isActive(): bool
  {
    return self::ACTIVE === $this;
  }

  public function label(): string
  {
    return match ($this) {
      self::ACTIVE => 'Active',
      self::ARCHIVED => 'Archived',
    };
  }
  // #endregion
}
