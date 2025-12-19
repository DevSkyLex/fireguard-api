<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Command\RevokeDevice\RevokeDeviceCommand;
use TrustedDevice\Application\UseCase\Command\RevokeDevice\RevokeDeviceHandler;

use function is_string;

/**
 * Processor RevokeDeviceProcessor.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class RevokeDeviceProcessor implements ProcessorInterface
{
    public function __construct(
        private RevokeDeviceHandler $handler,
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

        $this->handler->__invoke($command);

        return null;
    }
}
