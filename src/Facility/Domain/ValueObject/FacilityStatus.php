<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * Enum FacilityStatus.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum FacilityStatus: string
{
  /**
   * Case ACTIVE
   */
  case ACTIVE = 'active';

  /**
   * Case ARCHIVED
   */
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
  // #endregion
}
