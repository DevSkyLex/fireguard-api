<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{CreateInspectionCommand, CreateInspectionResult};
use Inspection\Presentation\Api\Dto\Input\Inspection\CreateInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Mission\Application\Contract\Resource\MissionResourceAssignment;
use Mission\Application\Service\MissionResourceManager;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Mission\Domain\Exception\{
  MissionConflictException,
  MissionNotFoundException,
  MissionResourceNotFoundException,
  ClientResourceAlreadyExistsException
};
use Mission\Domain\ValueObject\MissionResourceType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};

use function is_string;

/** @implements ProcessorInterface<CreateInspectionInput, InspectionOutput> */
final readonly class CreateInspectionProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateInspectionProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param InspectionOutputFactory $outputMapper the output mapper value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param ?MissionResourceManager $missionResourceManager the mission resource manager value
   * @param ?CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param ?EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private InspectionOutputFactory $outputMapper,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private ?MissionResourceManager $missionResourceManager = null,
    private ?CreationPreconditionGuard $creationPreconditionGuard = null,
    private ?EntityManagerInterface $entityManager = null,
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
   * @param array $uriVariables the uri variables value
   * @param array $context the context value
   *
   * @return InspectionOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InspectionOutput
  {
    /** @var CreateInspectionInput $data */
    if (null !== $data->mission && null !== $this->entityManager) {
      return $this->entityManager->wrapInTransaction(
        fn (): InspectionOutput => $this->processCreation($data, $operation, $uriVariables, $context),
      );
    }

    return $this->processCreation($data, $operation, $uriVariables, $context);
  }

  /**
   * Method processCreation.
   *
   * Executes one inspection creation and optional mission assignment.
   *
   * @since 1.0.0
   *
   * @param CreateInspectionInput $data the input data
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   */
  private function processCreation(CreateInspectionInput $data, Operation $operation, array $uriVariables, array $context): InspectionOutput
  {
    $resourceId = $uriVariables['id'] ?? null;
    if (is_string($resourceId)) {
      $this->creationPreconditionGuard?->assertCreateOnly();
      $data->clientId = $resourceId;
    } else {
      $resourceId = null;
    }
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? (null !== $data->organization ? ResourceIriParser::id($data->organization, 'organizations') : null);
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $permission = $this->missionPermission($data->mission, $user->getId()) ?? 'organization.inspection.write';
    if (!$this->authorization->hasPermission($user->getId(), $organizationId, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
    $this->assertOfflineCreate($data->clientId, null !== $resourceId);

    try {
      /** @var CreateInspectionResult $result */
      $result = $this->commandBus->dispatch(new CreateInspectionCommand(
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
      ));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = $this->outputMapper->fromCreateResult($result);
    $assignment = $this->attachToMission($result->inspectionId, $organizationId, $data->mission, $data->clientId);
    $output->mission = null === $assignment->missionId ? null : '/api/missions/' . $assignment->missionId;
    $output->recordStatus = $assignment->recordStatus;
    $output->revision = $assignment->revision;

    return $output;
  }

  /**
   * Method assertOfflineCreate.
   *
   * Executes the assert offline create operation.
   *
   * @since 1.0.0
   *
   * @param ?string $clientId the client id value
   * @param bool $createOnly the create only value
   */
  private function assertOfflineCreate(?string $clientId, bool $createOnly): void
  {
    if (null === $clientId || '' === $clientId || null === $this->missionResourceManager) {
      return;
    }

    try {
      $this->missionResourceManager->assertOfflineCreate(MissionResourceType::INSPECTION, $clientId);
    } catch (ClientResourceAlreadyExistsException $exception) {
      throw new ClientResourceAlreadyExistsHttpException(
        $createOnly ? Response::HTTP_PRECONDITION_FAILED : Response::HTTP_CONFLICT,
        $exception,
      );
    }
  }

  /**
   * Method missionPermission.
   *
   * Executes the mission permission operation.
   *
   * @since 1.0.0
   *
   * @param ?string $mission the mission value
   * @param string $userId the current user id value
   *
   * @return ?string the mission permission result
   */
  private function missionPermission(?string $mission, string $userId): ?string
  {
    if (null === $mission || null === $this->missionResourceManager) {
      return null;
    }
    try {
      return $this->missionResourceManager->mutationPermission(ResourceIriParser::id($mission, 'missions'), $userId);
    } catch (MissionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MissionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
  }

  /**
   * Method attachToMission.
   *
   * Executes the attach to mission operation.
   *
   * @since 1.0.0
   *
   * @param string $inspectionId the inspection id value
   * @param string $organizationId the organization id value
   * @param ?string $mission the mission value
   * @param ?string $clientId the client id value
   *
   * @return MissionResourceAssignment the attach to mission result
   */
  private function attachToMission(
    string $inspectionId,
    string $organizationId,
    ?string $mission,
    ?string $clientId,
  ): MissionResourceAssignment {
    if (null === $this->missionResourceManager) {
      return new MissionResourceAssignment(null, 'published', 1);
    }

    try {
      return $this->missionResourceManager->attach(
        MissionResourceType::INSPECTION,
        $inspectionId,
        $organizationId,
        null === $mission ? null : ResourceIriParser::id($mission, 'missions'),
        $clientId,
      );
    } catch (MissionNotFoundException|MissionResourceNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MissionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
  }
}
