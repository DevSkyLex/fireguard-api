<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use Shared\Application\Message\QueryHandler;

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
final readonly class CheckDeviceTrustedHandler implements QueryHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * CheckDeviceTrustedHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TrustedDeviceRepositoryPort $repository The trusted device repository.
   */
  public function __construct(
    private readonly TrustedDeviceRepositoryPort $repository,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the CheckDeviceTrustedQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CheckDeviceTrustedQuery $query The query.
   *
   * @return CheckDeviceTrustedResult The result.
   */
  public function __invoke(CheckDeviceTrustedQuery $query): CheckDeviceTrustedResult
  {
    // Hash the token to search
    $tokenHash = hash('sha256', $query->token);

    $device = $this->repository->findByToken(tokenHash: $tokenHash);

    if ($device === null) {
      return CheckDeviceTrustedResult::notTrusted();
    }

    // Verify token and user
    if (!$device->verify(plainToken: $query->token)) {
      return CheckDeviceTrustedResult::notTrusted();
    }

    if ($device->userId() !== $query->userId) {
      return CheckDeviceTrustedResult::notTrusted();
    }

    // Update last used
    $device->touch();
    $this->repository->save(device: $device);

    return CheckDeviceTrustedResult::trusted(
      deviceId: $device->id()->value,
      deviceName: $device->name(),
    );
  }
}
