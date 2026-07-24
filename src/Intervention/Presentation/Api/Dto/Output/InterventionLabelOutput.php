<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionLabelOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionLabelOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  public string $organization = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  public string $name = '';

  /**
   * Property color.
   *
   * @since 1.0.0
   */
  public string $color = '';

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  public string $updatedAt = '';
}
