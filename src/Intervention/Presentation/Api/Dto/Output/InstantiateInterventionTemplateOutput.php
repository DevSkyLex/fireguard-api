<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InstantiateInterventionTemplateOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InstantiateInterventionTemplateOutput
{
  /**
   * Property interventionId.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $interventionId = '';

  /**
   * Property number.
   *
   * @since 1.0.0
   */
  public int $number = 0;
}
