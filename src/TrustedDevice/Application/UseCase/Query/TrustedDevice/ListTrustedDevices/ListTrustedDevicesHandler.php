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
  // #region Constructor
  /**
   * Method __construct.
   *
   * Returns the caller’s trusted devices using the repository port.
   *
   * @access public
   *
   * @param TrustedDeviceRepositoryPort $repository port used to list trusted-device records for the user
   *
   * @return void
   */
  public function __construct(
    private TrustedDeviceRepositoryPort $repository,
  ) {
  }
  // #endregion

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
