<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Inspection\EditInspection\EditInspectionCommand;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Dto\Input\Inspection\EditInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function array_key_exists;
use function is_string;

/** @implements ProcessorInterface<EditInspectionInput, InspectionOutput> */
final readonly class EditInspectionProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InspectionOutput
  {
    /** @var EditInspectionInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException('Request not available.');
    }

    try {
      $payload = $request->toArray();
    } catch (Throwable $exception) {
      throw new BadRequestHttpException('Invalid JSON payload.', $exception);
    }

    if (!array_key_exists('equipmentId', $payload)
      && !array_key_exists('facilityId', $payload)
      && !array_key_exists('checklistId', $payload)
      && !array_key_exists('result', $payload)
      && !array_key_exists('performedAt', $payload)
      && !array_key_exists('notes', $payload)
      && !array_key_exists('signature', $payload)
    ) {
      throw new BadRequestHttpException('At least one field must be provided for update.');
    }

    try {
      $this->commandBus->dispatch(new EditInspectionCommand(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        equipmentId: $data->equipmentId,
        facilityId: $data->facilityId,
        checklistId: $data->checklistId,
        result: $data->result,
        performedAt: $data->performedAt,
        notes: $data->notes,
        signature: $data->signature,
        hasEquipmentId: array_key_exists('equipmentId', $payload),
        hasFacilityId: array_key_exists('facilityId', $payload),
        hasChecklistId: array_key_exists('checklistId', $payload),
        hasResult: array_key_exists('result', $payload),
        hasPerformedAt: array_key_exists('performedAt', $payload),
        hasNotes: array_key_exists('notes', $payload),
        hasSignature: array_key_exists('signature', $payload),
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InspectionAlreadyClosedException|InspectionAlreadySubmittedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception);
      if ($notFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $closed = $this->findInspectionAlreadyClosedException($exception);
      if ($closed instanceof InspectionAlreadyClosedException) {
        throw new ConflictHttpException($closed->getMessage(), $exception);
      }
      $submitted = $this->findInspectionAlreadySubmittedException($exception);
      if ($submitted instanceof InspectionAlreadySubmittedException) {
        throw new ConflictHttpException($submitted->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    /** @var GetInspectionResult $result */
    $result = $this->queryBus->ask(new GetInspectionQuery(
      organizationId: $organizationId,
      inspectionId: $inspectionId,
    ));

    return $this->mapResult($result);
  }

  private function mapResult(GetInspectionResult $result): InspectionOutput
  {
    $output = new InspectionOutput();
    $output->id = $result->inspectionId;
    $output->organizationId = $result->organizationId;
    $output->equipmentId = $result->equipmentId;
    $output->facilityId = $result->facilityId;
    $output->result = $result->result;
    $output->status = $result->status;
    $output->performedAt = $result->performedAt;
    $output->inspectorType = $result->inspectorType;
    $output->inspectorName = $result->inspectorName;
    $output->inspectorUserId = $result->inspectorUserId;
    $output->inspectorOrganizationName = $result->inspectorOrganizationName;
    $output->checklistId = $result->checklistId;
    $output->notes = $result->notes;
    $output->signature = $result->signature;
    $output->nonConformitiesCount = $result->nonConformitiesCount;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
}
