<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use InvalidArgumentException;

/**
 * Exception FacilityHierarchyException.
 *
 * @category Exception
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

  /**
   * Method incompatibleParentType.
   *
   * @since 1.0.0
   */
  public static function incompatibleParentType(string $type, ?string $parentType): self
  {
    return new self('Facility type "' . $type . '" cannot have parent type "' . ($parentType ?? 'none') . '".');
  }

  /**
   * Method parentUnavailable.
   *
   * @since 1.0.0
   */
  public static function parentUnavailable(): self
  {
    return new self('The parent facility is unavailable in this organization.');
  }

  /**
   * Method parentInactive.
   *
   * @since 1.0.0
   */
  public static function parentInactive(): self
  {
    return new self('The parent facility must be active.');
  }

  /**
   * Method parentPublicationIncompatible.
   *
   * @since 1.0.0
   */
  public static function parentPublicationIncompatible(): self
  {
    return new self('Published facilities require published parents; draft parents must belong to the same intervention.');
  }

  /**
   * Method unsupportedType.
   *
   * @since 1.0.0
   */
  public static function unsupportedType(string $type): self
  {
    return new self('Unsupported facility type "' . $type . '".');
  }
  // #endregion
}
