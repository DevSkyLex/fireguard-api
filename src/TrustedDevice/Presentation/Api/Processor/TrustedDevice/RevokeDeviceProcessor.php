<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};
use Throwable;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\RevokeDeviceCommand;
use TrustedDevice\Domain\Exception\TrustedDeviceNotFoundException;

use function is_string;

/**
 * Processor RevokeDeviceProcessor.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class RevokeDeviceProcessor implements ProcessorInterface
{
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $deviceId = $uriVariables['id'] ?? null;
    if (!is_string($deviceId)) {
      throw new BadRequestHttpException('Device ID is required.');
    }

    $command = new RevokeDeviceCommand(
      deviceId: $deviceId,
      userId: $user->getUserIdentifier(),
    );

    try {
      $this->commandBus->dispatch($command);
    } catch (Throwable $exception) {
      $notFound = $this->findDeviceNotFound($exception);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    return null;
  }

  private function findDeviceNotFound(Throwable $exception): ?TrustedDeviceNotFoundException
  {
    $current = $exception;
    while (null !== $current) {
      if ($current instanceof TrustedDeviceNotFoundException) {
        return $current;
      }

      $current = $current->getPrevious();
    }

    return null;
  }
}
