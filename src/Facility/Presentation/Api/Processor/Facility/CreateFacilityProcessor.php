<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityResult};
use Facility\Domain\Exception\{
  FacilityCodeAlreadyExistsException,
  FacilityHierarchyException,
  FacilityNotFoundException
};
use Facility\Presentation\Api\Dto\Input\Facility\CreateFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Http\CreationPreconditionGuard;
use InvalidArgumentException;
use Onboarding\Application\Contract\Setup\OrganizationSetupContext;
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Shared\Application\Contract\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor CreateFacilityProcessor.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateFacilityInput, FacilityOutput>
 */
final readonly class CreateFacilityProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateFacilityProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param Security $security the security value
   * @param ?CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private ?CreationPreconditionGuard $creationPreconditionGuard = null,
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
    return $this->processCreation($data, $uriVariables);
  }

  /**
   * Method processCreation.
   *
   * Executes one facility creation and optional intervention assignment.
   *
   * @since 1.0.0
   *
   * @param CreateFacilityInput $data the input data
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   */
  private function processCreation(CreateFacilityInput $data, array $uriVariables): FacilityOutput
  {
    $resourceId = $this->creationResourceId($data, $uriVariables);
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? (null !== $data->organization ? ResourceIriParser::id($data->organization, 'organizations') : null);
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    try {
      /** @var CreateFacilityResult $result */
      $result = $this->commandBus->dispatch(new CreateFacilityCommand(
        setupContext: OrganizationSetupContext::fromOptional($user->getId(), $data->onboardingSessionId, $data->onboardingItemKey),
        organizationId: $organizationId,
        interventionId: null !== $data->intervention ? ResourceIriParser::id($data->intervention, 'interventions') : null,
        type: $data->type,
        name: $data->name,
        parentFacilityId: $data->parentFacilityId,
        code: $data->code,
        address: $data->address,
        latitude: $data->latitude,
        longitude: $data->longitude,
        metadata: $data->metadata,
        resourceId: $resourceId,
        levelIndex: $data->levelIndex,
        elevationMeters: $data->elevationMeters,
        heightMeters: $data->heightMeters,
        customerId: $data->customerId,
        userId: $user->getId(),
        clientId: $data->clientId,
        createOnly: null !== $resourceId,
      ));
    } catch (FacilityCodeAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (FacilityHierarchyException $exception) {
      throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $this->throwMappedMessengerException($exception);
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
    $output->levelIndex = $result->levelIndex;
    $output->elevationMeters = $result->elevationMeters;
    $output->heightMeters = $result->heightMeters;
    $output->customerId = $result->customerId;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');
    $output->intervention = null === $result->interventionId ? null : '/api/interventions/' . $result->interventionId;
    $output->recordStatus = $result->recordStatus;
    $output->revision = $result->revision;

    return $output;
  }

  /**
   * Method creationResourceId
   *
   * Applies the canonical create-only precondition before forwarding an optional client identifier.
   *
   * @access private
   *
   * @param CreateFacilityInput $data the creation transport input
   * @param array<string, mixed> $uriVariables identifiers extracted from the request route
   *
   * @return ?string the canonical resource identifier, or null for the collection route
   */
  private function creationResourceId(CreateFacilityInput $data, array $uriVariables): ?string
  {
    $resourceId = $uriVariables['id'] ?? null;
    if (!is_string($resourceId)) {
      return null;
    }

    $this->creationPreconditionGuard?->assertCreateOnly();
    $data->clientId = $resourceId;

    return $resourceId;
  }

  /**
   * Preserves the HTTP mapping for domain exceptions wrapped by Messenger.
   *
   * @param MessengerRuntimeException $exception the wrapped dispatch failure
   */
  private function throwMappedMessengerException(MessengerRuntimeException $exception): never
  {
    $quotaExceeded = $this->findException($exception, OrganizationQuotaExceededException::class);
    if ($quotaExceeded instanceof OrganizationQuotaExceededException) {
      throw new ConflictHttpException($quotaExceeded->getMessage(), $exception);
    }

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
      throw new UnprocessableEntityHttpException($hierarchy->getMessage(), $exception);
    }

    $invalidArgument = $this->findInvalidArgumentException($exception);
    if ($invalidArgument instanceof InvalidArgumentException) {
      throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
    }

    throw $exception;
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
