<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;

/**
 * Handler CheckDeviceTrustedHandler
 * @final
 *
 * Handles device trust verification.
 *
 * @category Handler
 * @package TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckDeviceTrustedHandler
{
  public function __construct(
    private TrustedDeviceRepositoryPort $repository,
  ) {
  }

  public function __invoke(CheckDeviceTrustedQuery $query): CheckDeviceTrustedResult
  {
    // Hash the token to search
    $tokenHash = hash('sha256', $query->token);

    $device = $this->repository->findByToken($tokenHash);

    if ($device === null) {
      return CheckDeviceTrustedResult::notTrusted();
    }

    // Verify token and user
    if (!$device->verify($query->token)) {
      return CheckDeviceTrustedResult::notTrusted();
    }

    if ($device->userId() !== $query->userId) {
      return CheckDeviceTrustedResult::notTrusted();
    }

    // Update last used
    $device->touch();
    $this->repository->save($device);

    return CheckDeviceTrustedResult::trusted(
      deviceId: $device->id()->value,
      deviceName: $device->name(),
    );
  }
}
