<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Projection\GetWorkload;

use Workload\Application\Contract\Projection\WorkloadProjectionView;

/**
 * GetWorkloadResult.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetWorkloadResult implements \Shared\Application\Message\ResultMessage
{
  /**
   * @since 1.0.0
   *
   * @param WorkloadProjectionView $projection complete server-calculated workload projection
   * @param bool $canManageCapacity whether the caller may administer member availability
   * @param bool $canReadTeam whether the caller may read other members' workload
   * @param list<array{id: string, name: string}> $teams
   * @param list<array{id: string, name: string, avatarUrl: ?string, roleNames: list<string>}> $memberOptions organization-scoped picker identities
   * @param int $totalItems authorized members matching the filters before pagination
   * @param int $page resolved one-based member page
   * @param int $pageSize members per page
   */
  public function __construct(public WorkloadProjectionView $projection, public bool $canManageCapacity, public bool $canReadTeam, public array $teams, public array $memberOptions = [], public int $totalItems = 0, public int $page = 1, public int $pageSize = 10)
  {
  }
}
