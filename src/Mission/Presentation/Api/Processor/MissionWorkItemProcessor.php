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
use Mission\Presentation\Api\Dto\Input\{CreateMissionWorkItemInput, UpdateMissionWorkItemInput};
use Mission\Presentation\Api\Dto\Output\MissionWorkItemOutput;
use Mission\Presentation\Api\Factory\MissionWorkItemOutputFactory;
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
 * Processor MissionWorkItemProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, MissionWorkItemOutput|null>
 */
final readonly class MissionWorkItemProcessor implements ProcessorInterface
{
  use MissionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionWorkItemProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param MissionWorkItemOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private MissionWorkItemOutputFactory $mapper,
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
   * @return ?MissionWorkItemOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?MissionWorkItemOutput
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
    $payload = $data instanceof CreateMissionWorkItemInput
      ? [
        'missionId' => ResourceIriParser::id($data->mission, 'missions'),
        'action' => $data->action,
        'target' => $data->target,
        'resultResource' => $data->resultResource,
        'assigneeId' => null === $data->assignee ? null : ResourceIriParser::memberId($data->assignee),
        'source' => $data->source,
        'required' => $data->required,
      ]
      : ($data instanceof UpdateMissionWorkItemInput ? $this->updatePayload($data) : []);

    try {
      /** @var MutateMissionWorkflowResult $result */
      $result = $this->commandBus->dispatch(new MutateMissionWorkflowCommand(
        resource: 'work_item',
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
   * @param UpdateMissionWorkItemInput $input the input value
   *
   * @return array<string, mixed>
   */
  private function updatePayload(UpdateMissionWorkItemInput $input): array
  {
    $fields = $this->mergePatchFields->all();
    $payload = [];
    foreach (['resultResource', 'status', 'skipReason'] as $field) {
      if (array_key_exists($field, $fields)) {
        $payload[$field] = $input->{$field};
      }
    }
    if (array_key_exists('assignee', $fields)) {
      $payload['assigneeId'] = null === $input->assignee
        ? null
        : ResourceIriParser::memberId($input->assignee);
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
