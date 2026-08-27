<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use InvalidArgumentException;

/**
 * Exception FacilityHierarchyException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityHierarchyException extends InvalidArgumentException
{
  // #region Methods
  /**
   * Method cannotUseSelfAsParent.
   *
   * @since 1.0.0
   */
  public static function cannotUseSelfAsParent(): self
  {
    return new self('A facility cannot be its own parent.');
  }

  /**
   * Method parentInAnotherOrganization.
   *
   * @since 1.0.0
   */
  public static function parentInAnotherOrganization(): self
  {
    return new self('Parent facility must belong to the same organization.');
  }

  /**
   * Method hierarchyCycleDetected.
   *
   * @since 1.0.0
   */
  public static function hierarchyCycleDetected(): self
  {
    return new self('Cannot move facility: hierarchy cycle detected.');
  }

  /**
   * Method maxDepthExceeded.
   *
   * @since 1.0.0
   *
   * @param int $cap the configured maximum hierarchy depth
   */
  public static function maxDepthExceeded(int $cap): self
  {
    return new self('Facility hierarchy depth cap of ' . $cap . ' levels exceeded.');
  }
  // #endregion
}
