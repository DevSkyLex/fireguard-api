<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeAllDevices;

use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;

/**
 * Handler RevokeAllDevicesHandler
 * @final
 */
final readonly class RevokeAllDevicesHandler
{
  public function __construct(
    private TrustedDeviceRepositoryPort $repository,
  ) {
  }

  public function __invoke(RevokeAllDevicesCommand $command): int
  {
    return $this->repository->revokeAllForUser($command->userId);
  }
}
