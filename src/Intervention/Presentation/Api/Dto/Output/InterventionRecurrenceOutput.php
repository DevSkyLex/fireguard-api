<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionRecurrenceOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionRecurrenceOutput
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
   * Property template.
   *
   * @since 1.0.0
   */
  public string $template = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  public string $name = '';

  /**
   * Property site.
   *
   * @since 1.0.0
   */
  public ?string $site = null;

  /**
   * Property responsible.
   *
   * @since 1.0.0
   */
  public ?string $responsible = null;

  /**
   * Property frequency.
   *
   * @since 1.0.0
   */
  public string $frequency = '';

  /**
   * Property interval.
   *
   * @since 1.0.0
   */
  public int $interval = 1;

  /**
   * Property anchorDate.
   *
   * @since 1.0.0
   */
  public string $anchorDate = '';

  /**
   * Property timezone.
   *
   * @since 1.0.0
   */
  public string $timezone = '';

  /**
   * Property leadTimeDays.
   *
   * @since 1.0.0
   */
  public int $leadTimeDays = 7;

  /**
   * Property nextOccurrenceAt.
   *
   * @since 1.0.0
   */
  public string $nextOccurrenceAt = '';

  /**
   * Property lastMaterializedAt.
   *
   * @since 1.0.0
   */
  public ?string $lastMaterializedAt = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  public bool $isActive = true;

  /**
   * Property endAt.
   *
   * @since 1.0.0
   */
  public ?string $endAt = null;

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
