<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MissionTypeOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionTypeOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  public string $label = '';

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  public string $description = '';

  /**
   * Property actions.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $actions = [];
}
