<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\RevokeDevice;

use Shared\Application\Message\CommandHandler;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Domain\Exception\TrustedDeviceNotFoundException;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;

/**
 * Handler RevokeDeviceHandler
 * @final
 *
 * Handles revocation of a single 
 * trusted device.
 *
 * @category Handler
 * @package TrustedDevice\Application\UseCase\Command\RevokeDevice
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeDeviceHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes the handler with a 
   * device repository.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TrustedDeviceRepositoryPort $repository The device repository.
   */
  public function __construct(
    private readonly TrustedDeviceRepositoryPort $repository,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Revokes a trusted device.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RevokeDeviceCommand $command The command.
   *
   * @return RevokeDeviceResult The result.
   *
   * @throws TrustedDeviceNotFoundException If device not found or not owned by user.
   */
  public function __invoke(RevokeDeviceCommand $command): RevokeDeviceResult
  {
    $device = $this->repository->findById(id: new TrustedDeviceId(
      value: $command->deviceId
    ));

    if ($device === null || $device->userId() !== $command->userId) {
      throw TrustedDeviceNotFoundException::create(
        id: $command->deviceId,
      );
    }

    $device->revoke();
    $this->repository->save(device: $device);

    return new RevokeDeviceResult(
      success: true,
      deviceId: $command->deviceId,
    );
  }
  //#endregion
}
