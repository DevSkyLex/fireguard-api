<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Workload\Application\Contract\Planning\WorkloadTaskProposal;
use Workload\Application\UseCase\Query\Planning\AssessWorkload\{AssessWorkloadQuery, AssessWorkloadResult};
use Workload\Presentation\Api\Dto\Input\WorkloadAssessmentInput;
use Workload\Presentation\Api\Dto\Output\WorkloadAssessmentOutput;

use function is_string;

/**
 * WorkloadAssessmentProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, WorkloadAssessmentOutput>
 */
final readonly class WorkloadAssessmentProcessor implements ProcessorInterface
{
  /**
   * @since 1.0.0
   *
   * @param QueryBusPort $queries dispatches read use cases through the query bus
   * @param Security $security resolves the authenticated account at the HTTP boundary
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }

  /**
   * Dispatches a read-only simulation of task planning changes.
   *
   * @since 1.0.0
   *
   * @param mixed $data deserialized API input; authorization remains in the use case
   * @param Operation $operation API operation metadata
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return WorkloadAssessmentOutput server assessment presented for explicit overload consent
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WorkloadAssessmentOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!$data instanceof WorkloadAssessmentInput || !is_string($organizationId)) {
      throw new BadRequestHttpException('An assessment is required.');
    }
    $changes = [];
    foreach ($data->changes as $change) {
      $changes[] = new WorkloadTaskProposal($change->taskId, $change->memberId, $change->remainingMinutes, $change->startsOn, $change->endsOn);
    }
    /** @var AssessWorkloadResult $result */
    $result = $this->queries->ask(new AssessWorkloadQuery($user->getId(), $organizationId, $changes, $data->planInterventionId));

    return new WorkloadAssessmentOutput($organizationId, $result->assessment);
  }
}
