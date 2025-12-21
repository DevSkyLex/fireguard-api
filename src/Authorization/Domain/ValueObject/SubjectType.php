<?php

declare(strict_types=1);

namespace Authorization\Domain\ValueObject;

/**
 * Enum SubjectType.
 *
 * Represents the type of subject that can be assigned a role.
 * The SSO manages only users - company/organization context is
 * provided as external reference IDs.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum SubjectType: string
{
  // #region Cases
  /**
   * Case USER.
   *
   * Role assigned directly to a user (global or
   * scoped by external context).
   */
  case USER = 'user';
  // #endregion

  // #region Methods
  /**
   * Method label.
   *
   * Returns a human-readable label for
   * the subject type.
   *
   * @since 1.0.0
   *
   * @return string the human-readable label
   */
  public function label(): string
  {
    return match ($this) {
      self::USER => 'User',
    };
  }
  // #endregion
}
