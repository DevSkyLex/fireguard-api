<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * Enum OrganizationStatus.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationStatus: string
{
  /**
   * Case ACTIVE
   */
  case ACTIVE = 'active';

  /**
   * Case SUSPENDED
   */
  case SUSPENDED = 'suspended';

  /**
   * Case ARCHIVED
   */
  case ARCHIVED = 'archived';
  // #region Methods

  /**
   * Method fromIsActive.
   *
   * @since 1.0.0
   */
  public static function fromIsActive(bool $isActive): self
  {
    return $isActive ? self::ACTIVE : self::SUSPENDED;
  }

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
