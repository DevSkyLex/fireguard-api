<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionChangeOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionChangeOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  public string $intervention = '';

  /**
   * Property workItem.
   *
   * @since 1.0.0
   */
  public ?string $workItem = null;

  /**
   * Property resource.
   *
   * @since 1.0.0
   */
  public string $resource = '';

  /**
   * Property patch.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  public array $patch = [];

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  public string $status = 'proposed';

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  public int $revision = 1;

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
