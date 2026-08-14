<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\Facility\RestoreFacility\{RestoreFacilityCommand, RestoreFacilityResult};
use Facility\Domain\Exception\{FacilityArchivedException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
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
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

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

    $output = new FacilityOutput();
    $output->id = $result->facilityId;
    $output->organizationId = $result->organizationId;
    $output->parentFacilityId = $result->parentFacilityId;
    $output->type = $result->type;
    $output->name = $result->name;
    $output->code = $result->code;
    $output->status = $result->status;
    $output->address = $result->address;
    $output->metadata = $result->metadata;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }

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
}
