<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor\Assignment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Assignment\AssignTeamToIntervention\{AssignTeamToInterventionCommand, AssignTeamToInterventionResult};
use Intervention\Presentation\Api\Dto\Input\AssignInterventionTeamInput;
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Processor AssignTeamToInterventionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<AssignInterventionTeamInput, InterventionOutput|null>
 */
final readonly class AssignTeamToInterventionProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the AssignTeamToInterventionProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param InterventionOutputFactory $mapper the intervention output mapper
   * @param Security $security the security service
   * @param RevisionGuard $revisionGuard the optimistic-concurrency `If-Match` reader
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InterventionOutputFactory $mapper,
    private Security $security,
    private RevisionGuard $revisionGuard,
  ) {
  }

  /**
   * Method process.
   *
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ?InterventionOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionOutput
  {
    if (!$data instanceof AssignInterventionTeamInput) {
      throw new BadRequestHttpException('Invalid team assignment payload.');
    }

    $user = $this->user();
    $interventionId = $uriVariables['id'] ?? null;
    if (!is_string($interventionId) || '' === $interventionId) {
      throw new BadRequestHttpException('The intervention id URI parameter is required.');
    }

    // Outside the try: 428/412 are the guard's own HTTP answers, not workflow
    // exceptions to translate. The assignment writes `participants` through
    // the same workflow mutation a manual edit uses, and that path refuses a
    // mutation whose expected revision is unknown.
    $expectedRevision = $this->revisionGuard->expectedRevision();

    try {
      /** @var AssignTeamToInterventionResult $result */
      $result = $this->commandBus->dispatch(new AssignTeamToInterventionCommand(
        userId: $user->getId(),
        interventionId: $interventionId,
        teamId: $data->teamId,
        expectedRevision: $expectedRevision,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return null === $result->view ? null : $this->mapper->fromViewForCaller($result->view, $user->getId());
  }

  /**
   * Method user.
   *
   * Executes the user operation.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
