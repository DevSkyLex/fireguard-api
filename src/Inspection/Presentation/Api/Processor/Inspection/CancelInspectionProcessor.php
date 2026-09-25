<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Command\Inspection\CancelInspection\CancelInspectionCommand;
use Inspection\Domain\Exception\{InspectionAlreadyCancelledException, InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/** @implements ProcessorInterface<mixed, mixed> */
final readonly class CancelInspectionProcessor implements ProcessorInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    try {
      $this->commandBus->dispatch(new CancelInspectionCommand(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InspectionAlreadyClosedException|InspectionAlreadySubmittedException|InspectionAlreadyCancelledException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      throw $this->mapMessengerException($exception);
    }

    return null;
  }

  /**
   * Method mapMessengerException.
   *
   * Maps a wrapped command failure to its HTTP equivalent.
   *
   * @since 1.0.0
   *
   * @param MessengerRuntimeException $exception the wrapped command failure
   *
   * @return Throwable the mapped or original exception
   */
  private function mapMessengerException(MessengerRuntimeException $exception): Throwable
  {
    return match (true) {
      ($notFound = $this->findInspectionNotFoundException($exception)) instanceof InspectionNotFoundException => new NotFoundHttpException($notFound->getMessage(), $exception),
      ($closed = $this->findInspectionAlreadyClosedException($exception)) instanceof InspectionAlreadyClosedException => new ConflictHttpException($closed->getMessage(), $exception),
      ($cancelled = $this->findInspectionAlreadyCancelledException($exception)) instanceof InspectionAlreadyCancelledException => new ConflictHttpException($cancelled->getMessage(), $exception),
      ($submitted = $this->findInspectionAlreadySubmittedException($exception)) instanceof InspectionAlreadySubmittedException => new ConflictHttpException($submitted->getMessage(), $exception),
      ($invalidArgument = $this->findInvalidArgumentException($exception)) instanceof InvalidArgumentException => new BadRequestHttpException($invalidArgument->getMessage(), $exception),
      default => $exception,
    };
  }
}
