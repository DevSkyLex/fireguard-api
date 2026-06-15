<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Value object ReferencePack.
 *
 * Immutable description of a regulatory reference pack an intervention can be
 * bound to.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReferencePack
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   * @param string $country the country value
   * @param string $regime the regime value
   * @param string $name the name value
   * @param string $version the version value
   * @param list<string> $recommendedEquipmentTypes the recommended equipment types
   */
  public function __construct(
    public string $id,
    public string $country,
    public string $regime,
    public string $name,
    public string $version,
    public array $recommendedEquipmentTypes,
  ) {
  }
}
