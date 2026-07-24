<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;
use function is_int;
use function sprintf;

/**
 * OrganizationQuotaCatalog.
 *
 * Central list of the countable resources a subscription plan can cap. A plan
 * stores a map of resource key => integer limit; a resource absent from the map
 * is unlimited. This catalog is the source of truth for validating plan limit
 * maps.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationQuotaCatalog
{
  // #region Methods
  /**
   * Method keys.
   *
   * Returns every available quota resource key.
   *
   * @since 1.0.0
   *
   * @return list<string> the resource keys
   */
  public static function keys(): array
  {
    return array_map(
      static fn (OrganizationQuotaResource $resource): string => $resource->value,
      OrganizationQuotaResource::cases(),
    );
  }

  /**
   * Method isValid.
   *
   * Checks whether the provided key matches a known quota resource.
   *
   * @since 1.0.0
   *
   * @param string $key the resource key
   *
   * @return bool true when the key is a known resource, false otherwise
   */
  public static function isValid(string $key): bool
  {
    return null !== OrganizationQuotaResource::tryFrom($key);
  }

  /**
   * Method descriptionFor.
   *
   * Returns the description for a given resource key, or an empty string if unknown.
   *
   * @since 1.0.0
   *
   * @param string $key the resource key
   *
   * @return string the description
   */
  public static function descriptionFor(string $key): string
  {
    foreach (self::definitions() as $definition) {
      if ($definition['name'] === $key) {
        return $definition['description'];
      }
    }

    return '';
  }

  /**
   * Method normalizeLimits.
   *
   * Validates and normalizes a raw limit map, rejecting unknown resource keys
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
      $resource = OrganizationQuotaResource::tryFrom((string) $key);
      if (null === $resource) {
        throw InvalidValueException::because(sprintf('Unknown organization quota resource "%s".', (string) $key));
      }

      if (!is_int($value) || $value < 0) {
        throw InvalidValueException::because(sprintf('Quota limit for "%s" must be a non-negative integer.', $resource->value));
      }

      $normalized[$resource->value] = $value;
    }

    return $normalized;
  }

  /**
   * Method definitions.
   *
   * Returns all available organization quota resources.
   *
   * @since 1.0.0
   *
   * @return list<array{name: string, description: string}>
   */
  public static function definitions(): array
  {
    return [
      ['name' => 'members', 'description' => 'Maximum number of organization members'],
      ['name' => 'facilities', 'description' => 'Maximum number of facilities'],
      ['name' => 'equipment', 'description' => 'Maximum number of equipment items'],
      ['name' => 'inspections', 'description' => 'Maximum number of inspections'],
    ];
  }
  // #endregion
}
