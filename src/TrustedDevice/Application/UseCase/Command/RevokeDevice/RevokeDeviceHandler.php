<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeDevice;

use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Domain\Exception\TrustedDeviceNotFoundException;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;

/**
 * Handler RevokeDeviceHandler
 * @final
 */
final readonly class RevokeDeviceHandler
{
  public function __construct(
    private TrustedDeviceRepositoryPort $repository,
  ) {
  }

  public function __invoke(RevokeDeviceCommand $command): void
  {
    $device = $this->repository->findById(new TrustedDeviceId($command->deviceId));

    if ($device === null || $device->userId() !== $command->userId) {
      throw TrustedDeviceNotFoundException::create($command->deviceId);
    }

    $device->revoke();
    $this->repository->save($device);
  }
}
