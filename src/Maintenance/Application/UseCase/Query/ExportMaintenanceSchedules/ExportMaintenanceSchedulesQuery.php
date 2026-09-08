<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ExportMaintenanceSchedulesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportMaintenanceSchedulesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   * @param string $organizationId the organization the export is scoped to
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public ?string $facilityId = null,
    public ?string $equipmentType = null,
    public ?string $dueStatus = null,
  ) {
  }
  // #endregion
}
