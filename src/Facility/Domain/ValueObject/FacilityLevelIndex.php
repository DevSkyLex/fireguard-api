<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject FacilityLevelIndex.
 *
 * The stacking order of a floor within its parent building — ground floor 0,
 * basement -1 — as a bound rather than as an instance. A level index is a
 * lone nullable integer with no companion field to keep consistent, so unlike
 * {@see FacilityCoordinates} it is carried by the aggregates as a plain `?int`;
 * what needs a single home is the range, which both `Facility` and
 * `CanonicalFacility` enforce and which would otherwise be declared twice.
 *
 * Duplicates between sibling floors stay legal — a subtree move would produce
 * transient collisions — so nothing here asserts uniqueness.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityLevelIndex
{
  // #region Constants
  /**
   * The lowest legal stacking order for a floor.
   *
   * @since 1.0.0
   */
  public const int MIN = -100;

  /**
   * The highest legal stacking order for a floor.
   *
   * @since 1.0.0
   */
  public const int MAX = 200;
  // #endregion

  // #region Methods
  /**
   * Method normalize.
   *
   * Enforces the stacking-order bound in the Domain, not only in the
   * presentation DTOs' `Assert\Range` — the canonical PATCH surface reaches
   * the aggregate without passing through those.
   *
   * @since 1.0.0
   *
   * @param ?int $levelIndex the candidate stacking order, null clearing it
   *
   * @throws InvalidValueException when the level index falls outside the range
   *
   * @return ?int the accepted stacking order, null passing through untouched
   */
  public static function normalize(?int $levelIndex): ?int
  {
    if (null === $levelIndex) {
      return null;
    }

    if ($levelIndex < self::MIN || $levelIndex > self::MAX) {
      throw InvalidValueException::because('Facility level index must be between ' . self::MIN . ' and ' . self::MAX . '.');
    }

    return $levelIndex;
  }
  // #endregion
}
