<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MaintenanceScheduleOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceScheduleOutput
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
   * Property equipment.
   *
   * Equipment IRI.
   *
   * @since 1.0.0
   */
  public string $equipment = '';

  /**
   * Property facility.
   *
   * Facility IRI, if any.
   *
   * @since 1.0.0
   */
  public ?string $facility = null;

  /**
   * Property equipmentType.
   *
   * @since 1.0.0
   */
  public string $equipmentType = '';

  /**
   * Property intervalOverride.
   *
   * @since 1.0.0
   */
  public ?string $intervalOverride = null;

  /**
   * Property lastInspectionClosedAt.
   *
   * @since 1.0.0
   */
  public ?string $lastInspectionClosedAt = null;

  /**
   * Property nextDueAt.
   *
   * @since 1.0.0
   */
  public ?string $nextDueAt = null;

  /**
   * Property dueStatus.
   *
   * One of `unscheduled`, `up_to_date`, `due_soon`, `overdue`.
   *
   * @since 1.0.0
   */
  public string $dueStatus = '';

  /**
   * Property lastRemindedAt.
   *
   * @since 1.0.0
   */
  public ?string $lastRemindedAt = null;

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
