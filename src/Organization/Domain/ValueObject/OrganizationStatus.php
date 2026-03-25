<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * Enum OrganizationStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationStatus: string
{
  case ACTIVE = 'active';
  case SUSPENDED = 'suspended';
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

  public function label(): string
  {
    return match ($this) {
      self::ACTIVE => 'Active',
      self::SUSPENDED => 'Suspended',
      self::ARCHIVED => 'Archived',
    };
  }
  // #endregion
}
