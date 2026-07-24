<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules;

use DateTimeImmutable;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListMaintenanceSchedulesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMaintenanceSchedulesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $organizationId the organization id value
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   * @param ?DateTimeImmutable $dueBefore optional upper bound on the next due date
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public ?string $facilityId = null,
    public ?string $equipmentType = null,
    public ?string $dueStatus = null,
    public ?DateTimeImmutable $dueBefore = null,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
}
