<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionTemplateItemOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionTemplateItemOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property position.
   *
   * @since 1.0.0
   */
  public int $position = 0;

  /**
   * Property action.
   *
   * @since 1.0.0
   */
  public string $action = '';

  /**
   * Property target.
   *
   * @since 1.0.0
   */
  public ?string $target = null;

  /**
   * Property resultResource.
   *
   * @since 1.0.0
   */
  public ?string $resultResource = null;

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  public bool $required = true;

  /**
   * Property defaultAssignee.
   *
   * @since 1.0.0
   */
  public ?string $defaultAssignee = null;
}
