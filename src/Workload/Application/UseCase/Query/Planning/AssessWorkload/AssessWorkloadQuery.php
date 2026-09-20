<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Planning\AssessWorkload;

/**
 * AssessWorkloadQuery.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssessWorkloadQuery implements \Shared\Application\Message\QueryMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $userId authenticated account identifier used for authorization
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<\Workload\Application\Contract\Planning\WorkloadTaskProposal> $changes
   * @param ?string $planInterventionId draft intervention whose entire scope is being simulated
   */
  public function __construct(public string $userId, public string $organizationId, public array $changes, public ?string $planInterventionId = null)
  {
  }
}
