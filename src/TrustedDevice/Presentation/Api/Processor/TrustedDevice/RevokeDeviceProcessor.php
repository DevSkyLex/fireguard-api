<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor\TrustedDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\RevokeDeviceCommand;

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

    if (!$user instanceof SecurityUser) {
      throw new BadRequestHttpException('Authenticated user type is not supported.');
    }

    $deviceId = $uriVariables['id'] ?? null;
    if (!is_string($deviceId)) {
      throw new BadRequestHttpException('Device ID is required.');
    }

    $command = new RevokeDeviceCommand(
      deviceId: $deviceId,
      userId: $user->getId(),
    );

    $this->commandBus->dispatch($command);

    return null;
  }
}
