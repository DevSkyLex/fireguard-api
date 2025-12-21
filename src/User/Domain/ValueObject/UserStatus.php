<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

/**
 * Enum UserStatus.
 *
 * Represents the status of a user account.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum UserStatus: string
{
  // #region Cases
  /**
   * Case ACTIVE.
   *
   * User account is active and
   * can login.
   *
   * @since 1.0.0
   */
  case ACTIVE = 'active';

  /**
   * Case INACTIVE.
   *
   * User account is inactive (disabled
   * by admin).
   *
   * @since 1.0.0
   */
  case INACTIVE = 'inactive';

  /**
   * Case LOCKED.
   *
   * User account is locked (too many failed login
   * attempts or security issue).
   *
   * @since 1.0.0
   */
  case LOCKED = 'locked';

  /**
   * Case PENDING_VERIFICATION.
   *
   * User account is pending email
   * verification.
   *
   * @since 1.0.0
   */
  case PENDING_VERIFICATION = 'pending_verification';
  // #endregion

  // #region Constants
  /**
   * Constant VALUES.
   *
   * Array of all possible
   * status values.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array VALUES = [
    'active',
    'inactive',
    'locked',
    'pending_verification',
  ];
  // #endregion

  // #region Methods
  /**
   * Method isActive.
   *
   * Checks if the user status is active.
   *
   * @since 1.0.0
   *
   * @return bool true if active, false otherwise
   */
  public function isActive(): bool
  {
    return self::ACTIVE === $this;
  }

  /**
   * Method canLogin.
   *
   * Checks if the user can login with this status.
   *
   * @since 1.0.0
   *
   * @return bool true if can login, false otherwise
   */
  public function canLogin(): bool
  {
    return self::ACTIVE === $this;
  }

  /**
   * Method label.
   *
   * Returns a human-readable label for the status.
   *
   * @since 1.0.0
   *
   * @return string the human-readable label
   */
  public function label(): string
  {
    return match ($this) {
      self::ACTIVE               => 'Active',
      self::INACTIVE             => 'Inactive',
      self::LOCKED               => 'Locked',
      self::PENDING_VERIFICATION => 'Pending Verification',
    };
  }
  // #endregion
}
