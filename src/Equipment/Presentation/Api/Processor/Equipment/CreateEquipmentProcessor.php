<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\{AssignToFacilityCommand, AssignToFacilityResult};
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Presentation\Api\Dto\Input\Equipment\CreateEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\{EquipmentOutput, TagOutput};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
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

use function array_map;
use function is_string;

/**
 * Processor CreateEquipmentProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateEquipmentInput, EquipmentOutput>
 */
final readonly class CreateEquipmentProcessor implements ProcessorInterface
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateEquipmentProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param ?MissionResourceManager $missionResourceManager the mission resource manager value
   * @param ?CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param ?EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private ?MissionResourceManager $missionResourceManager = null,
    private ?CreationPreconditionGuard $creationPreconditionGuard = null,
    private ?EntityManagerInterface $entityManager = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EquipmentOutput
  {
    /** @var CreateEquipmentInput $data */
    if (null !== $data->mission && null !== $this->entityManager) {
      return $this->entityManager->wrapInTransaction(
        fn (): EquipmentOutput => $this->processCreation($data, $operation, $uriVariables, $context),
      );
    }

    return $this->processCreation($data, $operation, $uriVariables, $context);
  }

  /**
   * Method processCreation.
   *
   * Executes one equipment creation and optional mission assignment.
   *
   * @since 1.0.0
   *
   * @param CreateEquipmentInput $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  private function processCreation(CreateEquipmentInput $data, Operation $operation, array $uriVariables, array $context): EquipmentOutput
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

    $permission = $this->missionPermission($data->mission, $user->getId()) ?? 'organization.equipment.write';
    if (!$this->authorization->hasPermission($user->getId(), $organizationId, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
    $this->assertOfflineCreate($data->clientId, null !== $resourceId);

    try {
      /** @var CreateEquipmentResult $result */
      $result = $this->commandBus->dispatch(new CreateEquipmentCommand(
        organizationId: $organizationId,
        type: $data->type,
        subType: $data->subType,
        brand: $data->brand,
        model: $data->model,
        serialNumber: $data->serialNumber,
        locationLabel: $data->locationLabel,
        resourceId: $resourceId,
      ));
    } catch (EquipmentSerialNumberAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $serial = $this->findEquipmentSerialNumberAlreadyExistsException($exception);
      if ($serial instanceof EquipmentSerialNumberAlreadyExistsException) {
        throw new ConflictHttpException($serial->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = $this->mapResult($result);
    if (null !== $data->facility) {
      /** @var AssignToFacilityResult $assigned */
      $assigned = $this->commandBus->dispatch(new AssignToFacilityCommand(
        organizationId: $organizationId,
        equipmentId: $result->equipmentId,
        facilityId: ResourceIriParser::id($data->facility, 'facilities'),
      ));
      $output->facilityId = $assigned->facilityId;
      $output->installedAt = $assigned->installedAt;
      $output->updatedAt = $assigned->updatedAt->format('c');
    }
    $assignment = $this->attachToMission($result->equipmentId, $organizationId, $data->mission, $data->clientId);
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
      $this->missionResourceManager->assertOfflineCreate(MissionResourceType::EQUIPMENT, $clientId);
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
   * @param string $equipmentId the equipment id value
   * @param string $organizationId the organization id value
   * @param ?string $mission the mission value
   * @param ?string $clientId the client id value
   *
   * @return MissionResourceAssignment the attach to mission result
   */
  private function attachToMission(
    string $equipmentId,
    string $organizationId,
    ?string $mission,
    ?string $clientId,
  ): MissionResourceAssignment {
    if (null === $this->missionResourceManager) {
      return new MissionResourceAssignment(null, 'published', 1);
    }

    try {
      return $this->missionResourceManager->attach(
        MissionResourceType::EQUIPMENT,
        $equipmentId,
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

  /**
   * Method mapResult.
   *
   * @since 1.0.0
   */
  private function mapResult(CreateEquipmentResult $result): EquipmentOutput
  {
    $output = new EquipmentOutput();
    $output->id = $result->equipmentId;
    $output->organizationId = $result->organizationId;
    $output->facilityId = $result->facilityId;
    $output->type = $result->type;
    $output->subType = $result->subType;
    $output->brand = $result->brand;
    $output->model = $result->model;
    $output->serialNumber = $result->serialNumber;
    $output->locationLabel = $result->locationLabel;
    $output->status = $result->status;
    $output->installedAt = $result->installedAt;
    $output->commissionedAt = $result->commissionedAt;
    $output->tags = array_map(TagOutput::fromArray(...), $result->tags);
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
