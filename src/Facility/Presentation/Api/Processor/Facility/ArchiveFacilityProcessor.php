<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\Facility\ArchiveFacility\{ArchiveFacilityCommand, ArchiveFacilityResult};
use Facility\Domain\Exception\{FacilityHasActiveDependentsException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Factory\FacilityDetailOutputFactory;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor ArchiveFacilityProcessor.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, FacilityOutput>
 */
final readonly class ArchiveFacilityProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the detail factory, command dispatch, organization authorization, and caller identity for facility archival.
   *
   * @access public
   *
   * @param FacilityDetailOutputFactory $detail factory used to assemble the updated facility detail response
   * @param CommandBusPort $commandBus port used to dispatch the archive command
   * @param OrganizationAuthorizationPort $authorization port used to authorize the organization-scoped operation
   * @param Security $security security context used to obtain the acting member
   *
   * @return void
   */
  public function __construct(
    private FacilityDetailOutputFactory $detail,
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Checks facility write access, dispatches the archive command, and returns the updated facility details.
   *
   * @access public
   * @since 1.0.0
   *
   * @param null $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return FacilityOutput the archived facility
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $facilityId = $uriVariables['facilityId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('OrganizationId and facilityId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.write permission.');
    }

    try {
      /** @var ArchiveFacilityResult $result */
      $result = $this->commandBus->dispatch(new ArchiveFacilityCommand(
        organizationId: $organizationId,
        facilityId: $facilityId,
      ));
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $activeDependents = $this->findException($exception, FacilityHasActiveDependentsException::class);
      if ($activeDependents instanceof FacilityHasActiveDependentsException) {
        throw new ConflictHttpException($activeDependents->getMessage(), $exception);
      }

      $notFound = $this->findFacilityNotFoundException($exception);
      if ($notFound instanceof FacilityNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->detail->read($organizationId, $result->facilityId);
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
