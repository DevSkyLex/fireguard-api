<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\Facility\RestoreFacility\{RestoreFacilityCommand, RestoreFacilityResult};
use Facility\Domain\Exception\{FacilityArchivedException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Factory\FacilityDetailOutputFactory;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/** @implements ProcessorInterface<null, FacilityOutput> */
final readonly class RestoreFacilityProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Provides the facility reader, command bus, authorization check, and authenticated actor used by restoration.
   *
   * @access public
   *
   * @param FacilityDetailOutputFactory $detail reloads the restored facility details
   * @param CommandBusPort $commandBus dispatches facility restoration
   * @param OrganizationAuthorizationPort $authorization checks organization access
   * @param Security $security resolves the authenticated user
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
   * Checks write access, restores the facility, and returns its current details.
   *
   * @access public
   *
   * @param null $data unused operation input
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables route variables
   * @param array<string, mixed> $context processor context
   *
   * @return FacilityOutput the restored facility representation
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
      /** @var RestoreFacilityResult $result */
      $result = $this->commandBus->dispatch(new RestoreFacilityCommand(
        organizationId: $organizationId,
        facilityId: $facilityId,
      ));
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (FacilityArchivedException|InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findFacilityNotFoundException($exception);
      if ($notFound instanceof FacilityNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $archived = $this->findFacilityArchivedException($exception);
      if ($archived instanceof FacilityArchivedException) {
        throw new BadRequestHttpException($archived->getMessage(), $exception);
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
   * Searches wrapped Messenger failures for a facility-not-found exception.
   *
   * @access private
   *
   * @param Throwable $exception the failure to inspect
   *
   * @return ?FacilityNotFoundException the nested exception, when present
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
   * Method findFacilityArchivedException.
   *
   * Searches wrapped Messenger failures for a facility-archived exception.
   *
   * @access private
   *
   * @param Throwable $exception the failure to inspect
   *
   * @return ?FacilityArchivedException the nested exception, when present
   */
  private function findFacilityArchivedException(Throwable $exception): ?FacilityArchivedException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof FacilityArchivedException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof FacilityArchivedException) {
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
   * Searches wrapped Messenger failures for an invalid-argument exception.
   *
   * @access private
   *
   * @param Throwable $exception the failure to inspect
   *
   * @return ?InvalidArgumentException the nested exception, when present
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
