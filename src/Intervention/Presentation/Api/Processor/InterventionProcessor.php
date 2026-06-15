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
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionInput, UpdateInterventionInput};
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{CreationPreconditionGuard, MergePatchFields, RevisionGuard};
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

use function array_key_exists;
use function array_map;
use function in_array;
use function is_string;

/**
 * Processor InterventionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InterventionOutput|null>
 */
final readonly class InterventionProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param InterventionOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InterventionOutputFactory $mapper,
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
   * @return ?InterventionOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionOutput
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
    $payload = $data instanceof CreateInterventionInput
      ? $this->createPayload($data)
      : ($data instanceof UpdateInterventionInput ? $this->updatePayload($data) : []);

    try {
      /** @var MutateInterventionWorkflowResult $result */
      $result = $this->commandBus->dispatch(new MutateInterventionWorkflowCommand(
        resource: 'intervention',
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
   * Method createPayload.
   *
   * @since 1.0.0
   *
   * @param CreateInterventionInput $input the input value
   *
   * @return array<string, mixed>
   */
  private function createPayload(CreateInterventionInput $input): array
  {
    return [
      'organizationId' => ResourceIriParser::id($input->organization, 'organizations'),
      'type' => $input->type,
      'name' => $input->name,
      'siteId' => null === $input->site ? null : ResourceIriParser::id($input->site, 'facilities'),
      'responsibleId' => null === $input->responsible ? null : ResourceIriParser::memberId($input->responsible),
      'participants' => array_map(ResourceIriParser::memberId(...), $input->participants),
      'priority' => $input->priority,
      'plannedStartAt' => $input->plannedStartAt,
      'dueAt' => $input->dueAt,
    ];
  }

  /**
   * Method updatePayload.
   *
   * @since 1.0.0
   *
   * @param UpdateInterventionInput $input the input value
   *
   * @return array<string, mixed>
   */
  private function updatePayload(UpdateInterventionInput $input): array
  {
    $fields = $this->mergePatchFields->all();
    $payload = [];
    foreach (['name', 'status', 'priority', 'plannedStartAt', 'dueAt', 'reviewNote'] as $field) {
      if (array_key_exists($field, $fields)) {
        $payload[$field] = $input->{$field};
      }
    }
    if (array_key_exists('site', $fields)) {
      $payload['siteId'] = null === $input->site ? null : ResourceIriParser::id($input->site, 'facilities');
    }
    if (array_key_exists('responsible', $fields)) {
      $payload['responsibleId'] = null === $input->responsible
        ? null
        : ResourceIriParser::memberId($input->responsible);
    }
    if (array_key_exists('participants', $fields)) {
      $payload['participants'] = null === $input->participants
        ? null
        : array_map(ResourceIriParser::memberId(...), $input->participants);
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
