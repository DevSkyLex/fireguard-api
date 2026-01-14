<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use Shared\Application\Message\QueryHandler;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;

/**
 * Handler ListTrustedDevicesHandler.
 */
final readonly class ListTrustedDevicesHandler implements QueryHandler
{
  public function __construct(
    private TrustedDeviceRepositoryPort $repository,
  ) {
  }

  public function __invoke(ListTrustedDevicesQuery $query): ListTrustedDevicesResult
  {
    $devices = $this->repository->findAllByUserId($query->userId);

    $items = [];
    foreach ($devices as $device) {
      if (!$device->isValid()) {
        continue;
      }

      $items[] = new TrustedDeviceItemResult(
        id: $device->id()->value,
        name: $device->name(),
        lastUsedAt: $device->lastUsedAt(),
        expiresAt: $device->expiresAt(),
        createdAt: $device->createdAt(),
      );
    }

    return new ListTrustedDevicesResult(devices: $items);
  }
}
