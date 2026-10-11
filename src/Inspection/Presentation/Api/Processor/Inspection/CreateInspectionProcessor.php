<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{CreateInspectionCommand, CreateInspectionResult};
use Inspection\Presentation\Api\Dto\Input\Inspection\CreateInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard};
use Intervention\Application\Contract\Resource\ClientResourceAlreadyExistsException;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/** Translates inspection creation and offline preconditions into one owning transaction.
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateInspectionInput, InspectionOutput>
 */
final readonly class CreateInspectionProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commandBus, private InspectionOutputFactory $outputMapper, private Security $security, private CreationPreconditionGuard $creationPreconditionGuard)
  {
  }

  /** @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InspectionOutput
  {
    /** @var CreateInspectionInput $data */
    $resourceId = $uriVariables['id'] ?? null;
    if (is_string($resourceId)) {
      $this->creationPreconditionGuard->assertCreateOnly();
      $data->clientId = $resourceId;
    } else {
      $resourceId = null;
    }
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? (null !== $data->organization ? ResourceIriParser::id($data->organization, 'organizations') : null);
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $result = $this->dispatch(new CreateInspectionCommand(
      organizationId: $organizationId,
      equipmentId: $data->equipmentId,
      result: $data->result,
      performedAt: $data->performedAt,
      inspectorType: $data->inspectorType,
      inspectorName: $data->inspectorName,
      facilityId: $data->facilityId,
      checklistId: $data->checklistId,
      inspectorUserId: $data->inspectorUserId,
      inspectorOrganizationName: $data->inspectorOrganizationName,
      notes: $data->notes,
      signature: $data->signature,
      resourceId: $resourceId,
      interventionId: null !== $data->intervention ? ResourceIriParser::id($data->intervention, 'interventions') : null,
      clientId: $data->clientId,
      actorUserId: $user->getId(),
    ), $resourceId);
    $output = $this->outputMapper->fromCreateResult($result);
    $output->intervention = null === $result->interventionId ? null : '/api/interventions/' . $result->interventionId;
    $output->recordStatus = $result->recordStatus;
    $output->revision = $result->revision;

    return $output;
  }

  /**
   * Method dispatch
   *
   * Retains the operation-specific conflict status for wrapped client-identity failures.
   *
   * @access private
   *
   * @param CreateInspectionCommand $command the translated creation request
   * @param ?string $resourceId the offline PUT identity, absent for POST
   *
   * @return CreateInspectionResult the creation result
   */
  private function dispatch(CreateInspectionCommand $command, ?string $resourceId): CreateInspectionResult
  {
    try {
      /** @var CreateInspectionResult $result */
      $result = $this->commandBus->dispatch($command);

      return $result;
    } catch (Throwable $exception) {
      for ($current = $exception; null !== $current; $current = $current->getPrevious()) {
        if ($current instanceof ClientResourceAlreadyExistsException) {
          throw new ClientResourceAlreadyExistsHttpException(null !== $resourceId ? Response::HTTP_PRECONDITION_FAILED : Response::HTTP_CONFLICT, $current);
        }
      }

      throw $exception;
    }
  }
}
