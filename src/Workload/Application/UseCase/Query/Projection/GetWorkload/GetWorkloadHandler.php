<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Projection\GetWorkload;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort, TeamDirectoryPort};
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use Workload\Application\Contract\Projection\WorkloadProjectionView;
use Workload\Application\Port\Inbound\WorkloadProjectionPort;
use Workload\Domain\Exception\{WorkloadAccessDeniedException, WorkloadNotFoundException};
use Workload\Domain\ValueObject\LocalDate;

use function array_filter;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function intdiv;
use function max;
use function min;
use function strcasecmp;
use function strcmp;
use function usort;

/**
 * GetWorkloadHandler.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetWorkloadHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization organization membership and permission checks
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param TeamDirectoryPort $teams organization team directory used to select distinct members
   * @param WorkloadProjectionPort $projector computes daily demand without relying on paginated UI data
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationWorkforceDirectoryPort $workforce,
    private TeamDirectoryPort $teams,
    private WorkloadProjectionPort $projector,
  ) {
  }

  /**
   * Projects the authorized member set while retaining each member's entire organization workload.
   *
   * @since 1.0.0
   *
   * @param GetWorkloadQuery $query requested read scope and caller context
   *
   * @return GetWorkloadResult authorized workload, member options, and management capabilities
   */
  public function __invoke(GetWorkloadQuery $query): GetWorkloadResult
  {
    if (!$this->authorization->isMemberOf($query->userId, $query->organizationId)) {
      throw new WorkloadNotFoundException('Organization not found.');
    }
    LocalDate::fromString($query->from);
    LocalDate::fromString($query->to);
    if ($query->page < 1 || $query->pageSize < 1 || $query->pageSize > 100) {
      throw InvalidValueException::because('Page must be positive and pageSize must be between 1 and 100.');
    }
    if ($query->from > $query->to || new DateTimeImmutable($query->from)->diff(new DateTimeImmutable($query->to))->days > 92) {
      throw InvalidValueException::because('Request a workload period of at most 93 days.');
    }
    $members = $this->authorizedMemberIds($query);
    $view = $this->projector->project($query->organizationId, $query->from, $query->to, $members)->view;
    if ($query->overloadedOnly) {
      $view = self::onlyOverloaded($view);
    }
    $canReadTeam = $this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.workload.read');
    $canManageCapacity = $this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.workload.manage');
    $optionIds = array_map(static fn ($member): string => $member->id, array_values(array_filter($this->workforce->members($query->organizationId), static fn ($member): bool => $member->active && ($canReadTeam || $canManageCapacity || $member->userId === $query->userId))));
    $profiles = $this->workforce->profiles($query->organizationId, array_values(array_unique([...$optionIds, ...array_map(static fn ($member): string => $member->memberId, $view->members)])));
    $memberOptions = array_map(static fn (string $id): array => [
      'id' => $id,
      'name' => $profiles[$id]->name ?? $id,
      'avatarUrl' => $profiles[$id]->avatarUrl ?? null,
      'roleNames' => $profiles[$id]->roleNames ?? [],
    ], $optionIds);
    $namedMembers = array_map(static fn ($member): \Workload\Application\Contract\Projection\MemberWorkloadView => new \Workload\Application\Contract\Projection\MemberWorkloadView(
      $member->memberId,
      $member->days,
      $member->unallocated,
      $profiles[$member->memberId]->name ?? null,
    ), $view->members);
    usort($namedMembers, static fn ($left, $right): int => strcasecmp($left->displayName ?? $left->memberId, $right->displayName ?? $right->memberId) ?: strcmp($left->memberId, $right->memberId));
    usort($memberOptions, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']) ?: strcmp($left['id'], $right['id']));
    $totalItems = count($namedMembers);
    $page = min($query->page, max(1, intdiv(max(0, $totalItems - 1), $query->pageSize) + 1));
    $pagedMembers = array_slice($namedMembers, ($page - 1) * $query->pageSize, $query->pageSize);
    $view = new WorkloadProjectionView($view->startsOn, $view->endsOn, $view->today, $view->timezone, $view->firstDayOfWeek, $view->calculatedAt, $pagedMembers, $view->unassigned, $view->completeness);

    return new GetWorkloadResult(
      $view,
      $canManageCapacity,
      $canReadTeam,
      $canReadTeam ? $this->workforce->teams($query->organizationId) : [],
      $memberOptions,
      $totalItems,
      $page,
      $query->pageSize,
    );
  }

  /**
   * @since 1.0.0
   *
   * @return ?list<string> authorized member identifiers, or all members for a team reader
   */
  private function authorizedMemberIds(GetWorkloadQuery $query): ?array
  {
    $members = null;
    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.workload.read')) {
      $members = [$this->callerMemberId($query)];
    } elseif (null !== $query->teamId) {
      if (null === $this->teams->resolveTeam($query->organizationId, $query->teamId)) {
        throw new WorkloadNotFoundException('Team not found.');
      }
      $members = $this->teams->listActiveMemberIds($query->organizationId, $query->teamId);
    }
    if (null !== $query->memberId) {
      $members = null === $members || in_array($query->memberId, $members, true) ? [$query->memberId] : [];
    }

    return $members;
  }

  /**
   * Resolves the sole member a caller without team-read permission may see.
   *
   * @since 1.0.0
   *
   * @param GetWorkloadQuery $query requested read scope and caller context
   *
   * @return string the authorized caller member identifier
   */
  private function callerMemberId(GetWorkloadQuery $query): string
  {
    $callerId = null;
    foreach ($this->workforce->members($query->organizationId) as $member) {
      if ($member->active && $member->userId === $query->userId) {
        $callerId = $member->id;

        break;
      }
    }
    if (null === $callerId || null !== $query->teamId || (null !== $query->memberId && $callerId !== $query->memberId)) {
      throw new WorkloadAccessDeniedException('Team workload access is required.');
    }

    return $callerId;
  }

  /**
   * @since 1.0.0
   */
  private static function onlyOverloaded(WorkloadProjectionView $view): WorkloadProjectionView
  {
    $filtered = array_values(array_filter($view->members, static function ($member): bool {
      foreach ($member->days as $day) {
        if (($day->overloadMinutes ?? 0) > 0) {
          return true;
        }
      }
      foreach ($member->unallocated as $task) {
        if ('no_available_day' === $task->reason && 'committed' === $task->commitment) {
          return true;
        }
      }

      return false;
    }));

    return new WorkloadProjectionView($view->startsOn, $view->endsOn, $view->today, $view->timezone, $view->firstDayOfWeek, $view->calculatedAt, $filtered, $view->unassigned, $view->completeness);
  }
}
