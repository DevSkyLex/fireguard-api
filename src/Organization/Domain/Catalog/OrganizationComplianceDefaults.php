<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

/**
 * Catalog OrganizationComplianceDefaults.
 *
 * Compatibility scalar fallback for historical settings without a persisted reminder window. Effective policy maps come from owner-published Application contributions.
 *
 * Equipment types are referenced by their stable string value on purpose: the
 * Organization module never depends on the Equipment domain enum.
 *
 * @category Catalog
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationComplianceDefaults
{
  // #region Constants
  /**
   * Default number of days before a due date during which reminders fire.
   */
  public const int REMINDER_WINDOW_DAYS = 30;
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
}
