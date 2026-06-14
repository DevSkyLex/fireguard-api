<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO PublicationOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PublicationOutput
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
   * Property missionRevision.
   *
   * @since 1.0.0
   */
  public int $missionRevision = 0;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  public string $status = '';

  /**
   * Property error.
   *
   * @since 1.0.0
   */
  public ?string $error = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  public ?string $completedAt = null;
}
