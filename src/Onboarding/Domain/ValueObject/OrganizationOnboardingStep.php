<?php

declare(strict_types=1);

namespace Onboarding\Domain\ValueObject;

use function in_array;

/**
 * ValueObject OrganizationOnboardingStep.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingStep
{
  // #region Constants
  public const string CREATE_ORGANIZATION = 'create_organization';

  public const string INVITE_MEMBERS = 'invite_members';
  // #endregion

  // #region Methods
  /**
   * Method all.
   *
   * @since 1.0.0
   *
   * @return list<string> the known step keys
   */
  public static function all(): array
  {
    return [
      self::CREATE_ORGANIZATION,
      self::INVITE_MEMBERS,
    ];
  }

  /**
   * Method isValid.
   *
   * @since 1.0.0
   *
   * @param string $step the step key to validate
   *
   * @return bool true when step is supported
   */
  public static function isValid(string $step): bool
  {
    return in_array($step, self::all(), true);
  }
  // #endregion
}
