<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;

use function count;

/**
 * Handler ListTrustedDevicesHandler.
 */
final readonly class ListTrustedDevicesHandler implements QueryHandler
{
  public function __construct(
    private TrustedDeviceRepositoryPort $repository,
  ) {
  }

  /**
   * @return PaginatedResult<TrustedDeviceItemResult>
   */
  public function __invoke(ListTrustedDevicesQuery $query): PaginatedResult
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

    $total = count($items);

    return new PaginatedResult(
      items: $items,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
}
