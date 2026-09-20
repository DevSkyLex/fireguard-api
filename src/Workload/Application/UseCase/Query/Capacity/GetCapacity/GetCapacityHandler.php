<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Capacity\GetCapacity;

use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use Shared\Application\Message\QueryHandler;
use Workload\Application\Contract\Capacity\CapacityConfigurationView;
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Domain\Exception\{WorkloadAccessDeniedException, WorkloadNotFoundException};

use function array_filter;
use function array_values;

/**
 * GetCapacityHandler.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCapacityHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization organization membership and permission checks
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param CapacityRepositoryPort $capacities reads historical weeks and dated availability exceptions
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private OrganizationWorkforceDirectoryPort $workforce, private CapacityRepositoryPort $capacities)
  {
  }

  /**
   * Reads the authorized organization or member capacity history.
   *
   * @since 1.0.0
   *
   * @param GetCapacityQuery $query requested read scope and caller context
   *
   * @return GetCapacityResult authorized capacity configuration and its history
   */
  public function __invoke(GetCapacityQuery $query): GetCapacityResult
  {
    if (!$this->authorization->isMemberOf($query->userId, $query->organizationId)) {
      throw new WorkloadNotFoundException('Organization not found.');
    }
    if (null !== $query->memberId) {
      $target = null;
      foreach ($this->workforce->members($query->organizationId) as $member) {
        if ($member->id === $query->memberId) {
          $target = $member;
        }
      }
      if (null === $target) {
        throw new WorkloadNotFoundException('Member not found.');
      }
      if ($target->userId !== $query->userId
        && !$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.workload.read')
        && !$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.workload.manage')) {
        throw new WorkloadAccessDeniedException('Team workload access is required.');
      }
    }
    $weeks = array_values(array_filter(
      $this->capacities->weeks($query->organizationId),
      static fn ($week): bool => $week->scopeId === $query->organizationId || $week->scopeId === $query->memberId,
    ));
    $exceptions = array_values(array_filter(
      $this->capacities->exceptions($query->organizationId),
      static fn ($exception): bool => $exception->memberId === $query->memberId,
    ));

    return new GetCapacityResult(new CapacityConfigurationView($weeks, $exceptions));
  }
}
