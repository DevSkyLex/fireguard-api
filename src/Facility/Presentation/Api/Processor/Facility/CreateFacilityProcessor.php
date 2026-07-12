<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityResult};
use Facility\Domain\Exception\{
  FacilityCodeAlreadyExistsException,
  FacilityHierarchyException,
  FacilityNotFoundException
};
use Facility\Presentation\Api\Dto\Input\Facility\CreateFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
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
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationQuotaPort};
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Shared\Application\Exception\MessengerRuntimeException;
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
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor CreateFacilityProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateFacilityInput, FacilityOutput>
 */
final readonly class CreateFacilityProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateFacilityProcessor class.
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
    private OrganizationQuotaPort $quota,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityOutput
  {
    /** @var CreateFacilityInput $data */
    if (null !== $data->intervention && null !== $this->entityManager) {
      return $this->entityManager->wrapInTransaction(
        fn (): FacilityOutput => $this->processCreation($data, $operation, $uriVariables, $context),
      );
    }

    return $this->processCreation($data, $operation, $uriVariables, $context);
  }

  /**
   * Method processCreation.
   *
   * Executes one facility creation and optional intervention assignment.
   *
   * @since 1.0.0
   *
   * @param CreateFacilityInput $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  private function processCreation(CreateFacilityInput $data, Operation $operation, array $uriVariables, array $context): FacilityOutput
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

    $permission = $this->interventionPermission($data->intervention, $user->getId()) ?? 'organization.facilities.write';
    if (!$this->authorization->hasPermission($user->getId(), $organizationId, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
    $this->quota->assertCanAdd($organizationId, OrganizationQuotaResource::FACILITIES);
    $this->assertOfflineCreate($data->clientId, null !== $resourceId);

    try {
      /** @var CreateFacilityResult $result */
      $result = $this->commandBus->dispatch(new CreateFacilityCommand(
        organizationId: $organizationId,
        type: $data->type,
        name: $data->name,
        parentFacilityId: $data->parentFacilityId,
        code: $data->code,
        address: $data->address,
        latitude: $data->latitude,
        longitude: $data->longitude,
        metadata: $data->metadata,
        resourceId: $resourceId,
      ));
    } catch (FacilityCodeAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (FacilityHierarchyException|InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $codeConflict = $this->findFacilityCodeAlreadyExistsException($exception);
      if ($codeConflict instanceof FacilityCodeAlreadyExistsException) {
        throw new ConflictHttpException($codeConflict->getMessage(), $exception);
      }

      $notFound = $this->findFacilityNotFoundException($exception);
      if ($notFound instanceof FacilityNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $hierarchy = $this->findFacilityHierarchyException($exception);
      if ($hierarchy instanceof FacilityHierarchyException) {
        throw new BadRequestHttpException($hierarchy->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new FacilityOutput();
    $output->id = $result->facilityId;
    $output->organizationId = $result->organizationId;
    $output->parentFacilityId = $result->parentFacilityId;
    $output->type = $result->type;
    $output->name = $result->name;
    $output->code = $result->code;
    $output->status = $result->status;
    $output->address = $result->address;
    $output->latitude = $result->latitude;
    $output->longitude = $result->longitude;
    $output->metadata = $result->metadata;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');
    $assignment = $this->attachToIntervention($result->facilityId, $organizationId, $data->intervention, $data->clientId);
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
      $this->interventionResourceManager->assertOfflineCreate(InterventionResourceType::FACILITY, $clientId);
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
   * @param string $facilityId the facility id value
   * @param string $organizationId the organization id value
   * @param ?string $intervention the intervention value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the attach to intervention result
   */
  private function attachToIntervention(
    string $facilityId,
    string $organizationId,
    ?string $intervention,
    ?string $clientId,
  ): InterventionResourceAssignment {
    if (null === $this->interventionResourceManager) {
      return new InterventionResourceAssignment(null, 'published', 1);
    }

    try {
      return $this->interventionResourceManager->attach(
        InterventionResourceType::FACILITY,
        $facilityId,
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
   * Method findFacilityCodeAlreadyExistsException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?FacilityCodeAlreadyExistsException the resolved exception
   */
  private function findFacilityCodeAlreadyExistsException(Throwable $exception): ?FacilityCodeAlreadyExistsException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof FacilityCodeAlreadyExistsException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof FacilityCodeAlreadyExistsException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findFacilityNotFoundException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?FacilityNotFoundException the resolved exception
   */
  private function findFacilityNotFoundException(Throwable $exception): ?FacilityNotFoundException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof FacilityNotFoundException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof FacilityNotFoundException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findFacilityHierarchyException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?FacilityHierarchyException the resolved exception
   */
  private function findFacilityHierarchyException(Throwable $exception): ?FacilityHierarchyException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof FacilityHierarchyException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof FacilityHierarchyException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findInvalidArgumentException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?InvalidArgumentException the resolved exception
   */
  private function findInvalidArgumentException(Throwable $exception): ?InvalidArgumentException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof InvalidArgumentException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof InvalidArgumentException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }
  // #endregion
}
