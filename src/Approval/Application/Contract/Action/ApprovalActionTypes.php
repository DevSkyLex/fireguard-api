<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Action;

/**
 * Contract ApprovalActionTypes.
 *
 * Central catalog of regulated action types the four-eyes approval gate can
 * govern. Cross-module code (Inspection, Equipment, Organization) references
 * these constants instead of hard-coding the string — the single source of
 * truth locking the chain between `OrganizationApprovalSettings` keys, the
 * gate, and the tagged `approval.deferred_action_executor` adapters.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalActionTypes
{
  // #region Constants
  /**
   * Waiving a non-conformity (marking it `waived` instead of `done`).
   */
  public const string NC_WAIVER = 'nc_waiver';

  /**
   * Permanently decommissioning a piece of equipment.
   */
  public const string EQUIPMENT_DECOMMISSION = 'equipment_decommission';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this catalog only exposes constants.
   *
   * @since 1.0.0
   *
   * @codeCoverageIgnore Never executed: the constructor exists only to
   * forbid instantiation of this static catalogue.
   */
  private function __construct()
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method all.
   *
   * @static
   *
   * Returns every supported action type value.
   *
   * @since 1.0.0
   *
   * @return list<string> the action type values
   */
  public static function all(): array
  {
    return [self::NC_WAIVER, self::EQUIPMENT_DECOMMISSION];
  }

  /**
   * Method label.
   *
   * @static
   *
   * Returns the human-readable label for an action type value.
   *
   * @since 1.0.0
   *
   * @param string $value the action type value
   *
   * @return string the label, or the raw value when unknown
   */
  public static function label(string $value): string
  {
    return match ($value) {
      self::NC_WAIVER => 'Non-conformity waiver',
      self::EQUIPMENT_DECOMMISSION => 'Equipment decommission',
      default => $value,
    };
  }
  // #endregion
}
