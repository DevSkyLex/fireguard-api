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

  public const string SELECT_PLAN = 'select_plan';

  public const string INVITE_MEMBERS = 'invite_members';

  public const string CREATE_FIRST_FACILITY = 'create_first_facility';

  public const string CREATE_FIRST_EQUIPMENT = 'create_first_equipment';

  /**
   * Steps that cannot be skipped.
   *
   * @var list<string>
   */
  private const array REQUIRED_STEPS = [
    self::CREATE_ORGANIZATION,
    self::CREATE_FIRST_FACILITY,
    self::CREATE_FIRST_EQUIPMENT,
  ];
  // #endregion

  // #region Methods
  /**
   * Method all.
   *
   * Returns all step keys in their canonical execution order.
   *
   * @since 1.0.0
   *
   * @return list<string> the known step keys in order
   */
  public static function all(): array
  {
    return [
      self::CREATE_ORGANIZATION,
      self::SELECT_PLAN,
      self::INVITE_MEMBERS,
      self::CREATE_FIRST_FACILITY,
      self::CREATE_FIRST_EQUIPMENT,
    ];
  }

  /**
   * Method isRequired.
   *
   * @since 2.0.0
   *
   * @param string $step the step key to check
   *
   * @return bool true when the step is required and cannot be skipped
   */
  public static function isRequired(string $step): bool
  {
    return in_array($step, self::REQUIRED_STEPS, true);
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
