<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Authorization;

/**
 * Enum OrganizationAccessDecision.
 *
 * The outcome of an organization permission check, kept as three states
 * rather than a boolean so a caller can answer "is this record outside the
 * caller's scope?" separately from "is this member entitled?".
 *
 * A flat boolean collapses both denials into one, and a caller that maps
 * that single denial to 403 tells an outsider that a record they may not
 * read exists — the 403/404 difference is itself an existence oracle across
 * organizations. Callers map OUTSIDE_SCOPE to the same not-found response an
 * unknown identifier produces, and MISSING_PERMISSION to 403.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationAccessDecision: string
{
  case GRANTED = 'granted';
  case MISSING_PERMISSION = 'missing_permission';
  case OUTSIDE_SCOPE = 'outside_scope';

  // #region Methods
  /**
   * Method isGranted.
   *
   * Tells whether the permission is granted.
   *
   * @since 1.0.0
   *
   * @return bool true when the caller holds the permission
   */
  public function isGranted(): bool
  {
    return self::GRANTED === $this;
  }

  /**
   * Method isOutsideScope.
   *
   * Tells whether the caller has no active membership in the organization,
   * which makes every record owned by it invisible rather than forbidden.
   *
   * @since 1.0.0
   *
   * @return bool true when the organization is outside the caller's scope
   */
  public function isOutsideScope(): bool
  {
    return self::OUTSIDE_SCOPE === $this;
  }
  // #endregion
}
