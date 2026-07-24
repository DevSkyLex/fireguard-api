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
use Intervention\Application\Contract\Resource\InterventionResourceAssignment;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{
  ClientResourceAlreadyExistsException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionResourceNotFoundException
};
use Intervention\Domain\ValueObject\InterventionResourceType;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard};
use Shared\Presentation\Api\Http\ResourceIriParser;
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
  use MessengerExceptionUnwrapperTrait;

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
   * @param ?InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param ?CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param ?EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private ?InterventionResourceManager $interventionResourceManager = null,
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
    if (null !== $data->intervention && null !== $this->entityManager) {
      return $this->entityManager->wrapInTransaction(
        fn (): EquipmentOutput => $this->processCreation($data, $operation, $uriVariables, $context),
      );
    }

    return $this->processCreation($data, $operation, $uriVariables, $context);
  }

  /**
   * Method processCreation.
   *
   * Executes one equipment creation and optional intervention assignment.
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

    $permission = $this->interventionPermission($data->intervention, $user->getId()) ?? 'organization.equipment.write';
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
      $quotaExceeded = $this->findException($exception, OrganizationQuotaExceededException::class);
      if ($quotaExceeded instanceof OrganizationQuotaExceededException) {
        throw new ConflictHttpException($quotaExceeded->getMessage(), $exception);
      }

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
    $assignment = $this->attachToIntervention($result->equipmentId, $organizationId, $data->intervention, $data->clientId);
    $output->intervention = null === $assignment->interventionId ? null : '/api/interventions/' . $assignment->interventionId;
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
    if (null === $clientId || '' === $clientId || null === $this->interventionResourceManager) {
      return;
    }

    try {
      $this->interventionResourceManager->assertOfflineCreate(InterventionResourceType::EQUIPMENT, $clientId);
    } catch (ClientResourceAlreadyExistsException $exception) {
      throw new ClientResourceAlreadyExistsHttpException(
        $createOnly ? Response::HTTP_PRECONDITION_FAILED : Response::HTTP_CONFLICT,
        $exception,
      );
    }
  }

  /**
   * Method interventionPermission.
   *
   * Executes the intervention permission operation.
   *
   * @since 1.0.0
   *
   * @param ?string $intervention the intervention value
   * @param string $userId the current user id value
   *
   * @return ?string the intervention permission result
   */
  private function interventionPermission(?string $intervention, string $userId): ?string
  {
    if (null === $intervention || null === $this->interventionResourceManager) {
      return null;
    }

    try {
      return $this->interventionResourceManager->mutationPermission(ResourceIriParser::id($intervention, 'interventions'), $userId);
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
  }

  /**
   * Method attachToIntervention.
   *
   * Executes the attach to intervention operation.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the equipment id value
   * @param string $organizationId the organization id value
   * @param ?string $intervention the intervention value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the attach to intervention result
   */
  private function attachToIntervention(
    string $equipmentId,
    string $organizationId,
    ?string $intervention,
    ?string $clientId,
  ): InterventionResourceAssignment {
    if (null === $this->interventionResourceManager) {
      return new InterventionResourceAssignment(null, 'published', 1);
    }

    try {
      return $this->interventionResourceManager->attach(
        InterventionResourceType::EQUIPMENT,
        $equipmentId,
        $organizationId,
        null === $intervention ? null : ResourceIriParser::id($intervention, 'interventions'),
        $clientId,
      );
    } catch (InterventionNotFoundException|InterventionResourceNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
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
