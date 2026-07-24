<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{
  MutateInterventionWorkflowCommand,
  MutateInterventionWorkflowResult
};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionChangeInput, UpdateInterventionChangeInput};
use Intervention\Presentation\Api\Dto\Output\InterventionChangeOutput;
use Intervention\Presentation\Api\Factory\InterventionChangeOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{CreationPreconditionGuard, MergePatchFields, RevisionGuard};
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

use function array_key_exists;
use function in_array;
use function is_string;

/**
 * Processor InterventionChangeProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InterventionChangeOutput|null>
 */
final readonly class InterventionChangeProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionChangeProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param InterventionChangeOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InterventionChangeOutputFactory $mapper,
    private Security $security,
    private RequestStack $requestStack,
    private RevisionGuard $revisionGuard,
    private CreationPreconditionGuard $creationPreconditionGuard,
    private MergePatchFields $mergePatchFields,
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
   * @return ?InterventionChangeOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionChangeOutput
  {
    $method = $this->requestStack->getCurrentRequest()?->getMethod() ?? 'PATCH';
    $user = $this->user();
    $id = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : null;
    $action = in_array($method, ['POST', 'PUT'], true) ? 'create' : ('DELETE' === $method ? 'delete' : 'update');
    $createOnly = 'PUT' === $method;
    if ($createOnly) {
      $this->creationPreconditionGuard->assertCreateOnly();
    }
    $expectedRevision = in_array($method, ['PATCH', 'DELETE'], true)
      ? $this->revisionGuard->expectedRevision()
      : null;
    $payload = $data instanceof CreateInterventionChangeInput
      ? [
        'interventionId' => ResourceIriParser::id($data->intervention, 'interventions'),
        'workItemId' => null === $data->workItem ? null : ResourceIriParser::id($data->workItem, 'intervention-work-items'),
        'resource' => $data->resource,
        'patch' => $data->patch,
      ]
      : ($data instanceof UpdateInterventionChangeInput ? $this->updatePayload($data) : []);

    try {
      /** @var MutateInterventionWorkflowResult $result */
      $result = $this->commandBus->dispatch(new MutateInterventionWorkflowCommand(
        resource: 'change',
        action: $action,
        userId: $user->getId(),
        id: $id,
        payload: $payload,
        expectedRevision: $expectedRevision,
        createOnly: $createOnly,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return null === $result->view ? null : $this->mapper->fromView($result->view);
  }

  /**
   * Method updatePayload.
   *
   * @since 1.0.0
   *
   * @param UpdateInterventionChangeInput $input the input value
   *
   * @return array<string, mixed>
   */
  private function updatePayload(UpdateInterventionChangeInput $input): array
  {
    $fields = $this->mergePatchFields->all();
    $payload = [];
    if (array_key_exists('patch', $fields)) {
      $payload['patch'] = $input->patch;
    }
    if (array_key_exists('status', $fields)) {
      $payload['status'] = $input->status;
    }

    return $payload;
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
