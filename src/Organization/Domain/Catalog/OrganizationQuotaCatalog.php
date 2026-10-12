<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

use Shared\Domain\Exception\InvalidValueException;

use function is_int;
use function preg_match;
use function sprintf;

/**
 * OrganizationQuotaCatalog.
 *
 * Structural validation for historical plan limit maps. Application checks registration for new mutations independently from hydration.
 *
 * @category Catalog
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationQuotaCatalog
{
  // #region Methods
  /**
   * Method normalizeLimits.
   *
   * Validates and normalizes a raw limit map, rejecting malformed resource keys
   * and negative limits. Returns a map keyed by resource value with integer
   * limits; unlimited resources are simply absent.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $limits the raw limit map
   *
   * @return array<string, int> the normalized limit map
   */
  public static function normalizeLimits(array $limits): array
  {
    $normalized = [];

    foreach ($limits as $key => $value) {
      $key = (string) $key;
      if (1 !== preg_match('/^[a-z][a-z0-9_]{0,79}$/D', $key)) {
        throw InvalidValueException::because(sprintf('Invalid organization quota resource "%s".', $key));
      }
      if (!is_int($value) || $value < 0) {
        throw InvalidValueException::because(sprintf('Quota limit for "%s" must be a non-negative integer.', $key));
      }
      $normalized[$key] = $value;
    }

    return $normalized;
  }

  // #endregion
}
