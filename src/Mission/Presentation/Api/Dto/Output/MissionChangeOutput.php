<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MissionChangeOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionChangeOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property mission.
   *
   * @since 1.0.0
   */
  public string $mission = '';

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
