<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Presentation\Api\Dto\Input\Equipment\CreateEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{
  ClientResourceAlreadyExistsException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionResourceNotFoundException
};
use Intervention\Domain\ValueObject\InterventionResourceType;
use InvalidArgumentException;
use Onboarding\Application\Contract\Setup\OrganizationSetupContext;
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
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

use function is_string;

/**
 * Processor CreateEquipmentProcessor.
 *
 * @category Processor
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
   * @param EquipmentOutputFactory $outputFactory the shared Result -> EquipmentOutput mapper
   * @param ?InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param ?CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param ?EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private EquipmentOutputFactory $outputFactory,
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
        fn (): EquipmentOutput => $this->processCreation($data, $uriVariables),
      );
    }

    return $this->processCreation($data, $uriVariables);
  }

  /**
   * Method processCreation.
   *
   * Executes one equipment creation and optional intervention assignment.
   *
   * @since 1.0.0
   *
   * @param CreateEquipmentInput $data the input data
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   */
  private function processCreation(CreateEquipmentInput $data, array $uriVariables): EquipmentOutput
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

    $permission = $this->interventionPermission($data->intervention, $user->getId(), $organizationId) ?? 'organization.equipment.write';
    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
    $this->assertOfflineCreate($data->clientId, null !== $resourceId);

    $result = $this->dispatchCreation($data, $organizationId, $resourceId, $user->getId());

    $output = $this->outputFactory->fromView($result);
    $output->intervention = null === $result->interventionId ? null : '/api/interventions/' . $result->interventionId;
    $output->recordStatus = $result->recordStatus;
    $output->revision = $result->revision;

    return $output;
  }

  /**
   * Dispatches the creation command and preserves its HTTP error mapping.
   *
   * @since 1.0.0
   *
   * @param CreateEquipmentInput $data the validated input
   * @param string $organizationId the target organization ID
   * @param ?string $resourceId the optional offline resource ID
   * @param string $userId the authenticated creator ID
   *
   * @return CreateEquipmentResult the created equipment
   */
  private function dispatchCreation(
    CreateEquipmentInput $data,
    string $organizationId,
    ?string $resourceId,
    string $userId,
  ): CreateEquipmentResult {
    try {
      /** @var CreateEquipmentResult */
      return $this->commandBus->dispatch(new CreateEquipmentCommand(
        setupContext: OrganizationSetupContext::fromOptional($userId, $data->onboardingSessionId, $data->onboardingItemKey),
        facilityId: null !== $data->facility ? ResourceIriParser::id($data->facility, 'facilities') : null,
        interventionId: null !== $data->intervention ? ResourceIriParser::id($data->intervention, 'interventions') : null,
        clientId: $data->clientId,
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
    } catch (InterventionNotFoundException|InterventionResourceNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (ClientResourceAlreadyExistsException $exception) {
      throw new ClientResourceAlreadyExistsHttpException(
        null !== $resourceId ? Response::HTTP_PRECONDITION_FAILED : Response::HTTP_CONFLICT,
        $exception,
      );
    } catch (MessengerRuntimeException $exception) {
      $this->rethrowWrappedCreationException($exception, $resourceId);
    }
  }

  /**
   * Method rethrowWrappedCreationException.
   *
   * Maps wrapped handler failures in the same precedence as direct failures.
   *
   * @access private
   * @since 1.0.0
   *
   * @param MessengerRuntimeException $exception the command-bus failure chain
   * @param ?string $resourceId the offline creation precondition identifier
   *
   * @return never the mapped HTTP failure is always thrown
   */
  private function rethrowWrappedCreationException(MessengerRuntimeException $exception, ?string $resourceId): never
  {
    $identityConflict = $this->findException($exception, ClientResourceAlreadyExistsException::class);
    if ($identityConflict instanceof ClientResourceAlreadyExistsException) {
      throw new ClientResourceAlreadyExistsHttpException(
        null !== $resourceId ? Response::HTTP_PRECONDITION_FAILED : Response::HTTP_CONFLICT,
        $identityConflict,
      );
    }
    foreach ([InterventionNotFoundException::class, InterventionResourceNotFoundException::class] as $type) {
      $missing = $this->findException($exception, $type);
      if (null !== $missing) {
        throw new NotFoundHttpException($missing->getMessage(), $exception);
      }
    }
    $conflict = $this->findException($exception, InterventionConflictException::class);
    if (null !== $conflict) {
      throw new ConflictHttpException($conflict->getMessage(), $exception);
    }
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
   * @param string $organizationId the organization id the caller claims the equipment
   *                               belongs to — required to belong to the same organization as the intervention,
   *                               checked inside mutationPermission() before any status-derived branch
   *
   * @return ?string the intervention permission result
   */
  private function interventionPermission(?string $intervention, string $userId, string $organizationId): ?string
  {
    if (null === $intervention || null === $this->interventionResourceManager) {
      return null;
    }

    try {
      return $this->interventionResourceManager->mutationPermission(ResourceIriParser::id($intervention, 'interventions'), $userId, $organizationId);
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
  }

  // #endregion
}
