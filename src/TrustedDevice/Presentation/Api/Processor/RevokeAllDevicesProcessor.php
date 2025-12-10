<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Command\RevokeAllDevices\RevokeAllDevicesCommand;
use TrustedDevice\Application\UseCase\Command\RevokeAllDevices\RevokeAllDevicesHandler;

/**
 * Processor RevokeAllDevicesProcessor
 * @final
 *
 * @implements ProcessorInterface<mixed, array{revoked: int}>
 */
final readonly class RevokeAllDevicesProcessor implements ProcessorInterface
{
  public function __construct(
    private RevokeAllDevicesHandler $handler,
    private Security $security,
  ) {
  }

  /**
   * @return array{revoked: int}
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if ($user === null) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $command = new RevokeAllDevicesCommand(
      userId: $user->getUserIdentifier(),
    );

    $result = $this->handler->__invoke($command);

    return ['revoked' => $result->revokedCount];
  }
}
