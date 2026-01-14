<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices;

use Shared\Application\Message\CommandHandler;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;

/**
 * Handler RevokeAllDevicesHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllDevicesHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the handler with a
   * device repository.
   *
   * @since 1.0.0
   *
   * @param TrustedDeviceRepositoryPort $repository the device repository
   */
  public function __construct(
    private readonly TrustedDeviceRepositoryPort $repository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Revokes all trusted devices
   * for a user.
   *
   * @since 1.0.0
   *
   * @param RevokeAllDevicesCommand $command the command
   *
   * @return RevokeAllDevicesResult the result
   */
  public function __invoke(RevokeAllDevicesCommand $command): RevokeAllDevicesResult
  {
    $count = $this->repository->revokeAllForUser(userId: $command->userId);

    return new RevokeAllDevicesResult(revokedCount: $count);
  }
  // #endregion
}
