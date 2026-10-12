<?php

declare(strict_types=1);

namespace Onboarding\Domain\ValueObject;

use function preg_match;

/**
 * ValueObject OrganizationOnboardingStep.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingStep
{
  // #region Constants
  /**
   * Constant CREATE_ORGANIZATION
   */
  public const string CREATE_ORGANIZATION = 'create_organization';

  /**
   * Constant SELECT_PLAN
   */
  public const string SELECT_PLAN = 'select_plan';

  /**
   * Constant INVITE_MEMBERS
   */
  public const string INVITE_MEMBERS = 'invite_members';

  /**
   * Constant CREATE_FIRST_FACILITY
   */
  public const string CREATE_FIRST_FACILITY = 'create_first_facility';

  /**
   * Constant CREATE_FIRST_EQUIPMENT
   */
  public const string CREATE_FIRST_EQUIPMENT = 'create_first_equipment';

  // #endregion

  // #region Methods
  /**
   * Method isValid.
   *
   * @since 1.0.0
   *
   * @param string $step the step key to validate
   *
   * @return bool true when the persisted key has valid syntax, independently of installed contributors
   */
  public static function isValid(string $step): bool
  {
    return 1 === preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $step);
  }
  // #endregion
}
