<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ReferencePackOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ReferencePackOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property country.
   *
   * @since 1.0.0
   */
  public string $country = '';

  /**
   * Property regime.
   *
   * @since 1.0.0
   */
  public string $regime = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  public string $name = '';

  /**
   * Property version.
   *
   * @since 1.0.0
   */
  public string $version = '';

  /**
   * Property recommendedEquipmentTypes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $recommendedEquipmentTypes = [];
}
