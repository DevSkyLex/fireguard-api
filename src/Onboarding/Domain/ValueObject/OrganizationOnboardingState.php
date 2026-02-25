<?php

declare(strict_types=1);

namespace Onboarding\Domain\ValueObject;

use function in_array;

/**
 * ValueObject OrganizationOnboardingState.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingState
{
  // #region Constants
  public const string IN_PROGRESS = 'in_progress';

  public const string COMPLETED = 'completed';

  public const string BLOCKED = 'blocked';
  // #endregion

  // #region Methods
  /**
   * Method all.
   *
   * @since 1.0.0
   *
   * @return list<string> the known state keys
   */
  public static function all(): array
  {
    return [
      self::IN_PROGRESS,
      self::COMPLETED,
      self::BLOCKED,
    ];
  }

  /**
   * Method isValid.
   *
   * @since 1.0.0
   *
   * @param string $state the state key to validate
   *
   * @return bool true when state is supported
   */
  public static function isValid(string $state): bool
  {
    return in_array($state, self::all(), true);
  }
  // #endregion
}
