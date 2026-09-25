<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree\{DuplicateFacilitySubtreeCommand, DuplicateFacilitySubtreeResult};
use Facility\Domain\Exception\{
  FacilityHierarchyException,
  FacilityNotFoundException,
  FacilitySubtreeSourceArchivedException,
  FacilitySubtreeTooLargeException
};
use Facility\Presentation\Api\Dto\Input\Facility\DuplicateFacilitySubtreeInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Factory\FacilityDetailOutputFactory;
use InvalidArgumentException;
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
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
 * Processor DuplicateFacilitySubtreeProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<DuplicateFacilitySubtreeInput, FacilityOutput>
 */
final readonly class DuplicateFacilitySubtreeProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
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
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityOutput
  {
    /** @var DuplicateFacilitySubtreeInput $data */
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
      /** @var DuplicateFacilitySubtreeResult $result */
      $result = $this->commandBus->dispatch(new DuplicateFacilitySubtreeCommand(
        organizationId: $organizationId,
        facilityId: $facilityId,
        name: $data->name,
        parentFacilityId: $data->parentFacilityId,
      ));
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (FacilitySubtreeSourceArchivedException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (FacilitySubtreeTooLargeException $exception) {
      throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      throw $this->mapMessengerException($exception);
    }

    return $this->detail->read($organizationId, $result->facilityId);
  }

  private function mapMessengerException(MessengerRuntimeException $exception): Throwable
  {
    $quotaExceeded = $this->findException($exception, OrganizationQuotaExceededException::class);
    $notFound = $this->findFacilityNotFoundException($exception);
    $archivedSource = $this->findException($exception, FacilitySubtreeSourceArchivedException::class);
    $tooLarge = $this->findException($exception, FacilitySubtreeTooLargeException::class);
    $hierarchy = $this->findException($exception, FacilityHierarchyException::class);
    $invalidArgument = $this->findException($exception, InvalidArgumentException::class);

    return match (true) {
      $quotaExceeded instanceof OrganizationQuotaExceededException => new ConflictHttpException($quotaExceeded->getMessage(), $exception),
      $notFound instanceof FacilityNotFoundException => new NotFoundHttpException($notFound->getMessage(), $exception),
      $archivedSource instanceof FacilitySubtreeSourceArchivedException => new ConflictHttpException($archivedSource->getMessage(), $exception),
      $tooLarge instanceof FacilitySubtreeTooLargeException => new UnprocessableEntityHttpException($tooLarge->getMessage(), $exception),
      $hierarchy instanceof FacilityHierarchyException => new BadRequestHttpException($hierarchy->getMessage(), $exception),
      $invalidArgument instanceof InvalidArgumentException => new BadRequestHttpException($invalidArgument->getMessage(), $exception),
      default => $exception,
    };
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
  // #endregion
}
