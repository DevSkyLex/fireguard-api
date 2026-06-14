<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Mission\Application\UseCase\Command\Workflow\MutateMissionWorkflow\{
  MutateMissionWorkflowCommand,
  MutateMissionWorkflowResult
};
use Mission\Presentation\Api\Dto\Input\{CreateMissionChangeInput, UpdateMissionChangeInput};
use Mission\Presentation\Api\Dto\Output\MissionChangeOutput;
use Mission\Presentation\Api\Factory\MissionChangeOutputFactory;
use Mission\Presentation\Api\Trait\MissionWorkflowExceptionMapperTrait;
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
 * Processor MissionChangeProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, MissionChangeOutput|null>
 */
final readonly class MissionChangeProcessor implements ProcessorInterface
{
  use MissionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionChangeProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param MissionChangeOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private MissionChangeOutputFactory $mapper,
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
   * @return ?MissionChangeOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?MissionChangeOutput
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
    $payload = $data instanceof CreateMissionChangeInput
      ? [
        'missionId' => ResourceIriParser::id($data->mission, 'missions'),
        'workItemId' => null === $data->workItem ? null : ResourceIriParser::id($data->workItem, 'mission-work-items'),
        'resource' => $data->resource,
        'patch' => $data->patch,
      ]
      : ($data instanceof UpdateMissionChangeInput ? $this->updatePayload($data) : []);

    try {
      /** @var MutateMissionWorkflowResult $result */
      $result = $this->commandBus->dispatch(new MutateMissionWorkflowCommand(
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
   * @param UpdateMissionChangeInput $input the input value
   *
   * @return array<string, mixed>
   */
  private function updatePayload(UpdateMissionChangeInput $input): array
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
