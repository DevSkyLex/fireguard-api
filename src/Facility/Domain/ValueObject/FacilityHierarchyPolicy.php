<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityHierarchyException;

/**
 * ValueObject FacilityHierarchyPolicy.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityHierarchyPolicy
{
  // #region Methods
  /**
   * Method allowsParent.
   *
   * @since 1.0.0
   *
   * @return bool whether the immediate relationship belongs to the location taxonomy
   */
  public static function allowsParent(FacilityType $type, ?FacilityType $parentType): bool
  {
    return match ($type) {
      FacilityType::SITE => null === $parentType,
      FacilityType::BUILDING => FacilityType::SITE === $parentType,
      FacilityType::FLOOR => FacilityType::BUILDING === $parentType,
      FacilityType::ZONE, FacilityType::AREA => null !== $parentType,
    };
  }

  /**
   * Method assertParent.
   *
   * @since 1.0.0
   */
  public static function assertParent(FacilityType $type, ?FacilityType $parentType): void
  {
    if (!self::allowsParent($type, $parentType)) {
      throw FacilityHierarchyException::incompatibleParentType($type->value, $parentType?->value);
    }
  }
  // #endregion
}
