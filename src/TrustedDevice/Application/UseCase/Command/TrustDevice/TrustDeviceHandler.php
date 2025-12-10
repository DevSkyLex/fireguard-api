<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustDevice;

use Shared\Application\Message\CommandHandler;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Domain\Model\TrustedDevice;
use TrustedDevice\Domain\ValueObject\DeviceFingerprint;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;
use Shared\Application\Factory\UuidFactory;

/**
 * Handler TrustDeviceHandler
 * @final
 *
 * Handles device trust registration.
 *
 * @category Handler
 * @package TrustedDevice\Application\UseCase\Command\TrustDevice
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustDeviceHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the handler with the 
   * device repository and UUID factory.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TrustedDeviceRepositoryPort $repository The device repository.
   * @param UuidFactory $uuidFactory The UUID factory.
   */
  public function __construct(
    private readonly TrustedDeviceRepositoryPort $repository,
    private readonly UuidFactory $uuidFactory,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the trust device command.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TrustDeviceCommand $command The command.
   *
   * @return TrustDeviceResult The result.
   */
  public function __invoke(TrustDeviceCommand $command): TrustDeviceResult
  {
    // Create fingerprint
    $fingerprint = DeviceFingerprint::create(
      userAgent: $command->userAgent,
      ipAddress: $command->ipAddress,
      acceptLanguage: $command->acceptLanguage,
    );

    // Check if device already exists
    $existing = $this->repository->findByUserIdAndFingerprint(
      userId: $command->userId,
      fingerprint: $fingerprint->value,
    );

    if ($existing !== null && $existing->isValid()) {
      // Return existing valid device
      return new TrustDeviceResult(
        deviceId: $existing->id()->value,
        token: $existing->token()->plain(),
        deviceName: $existing->name(),
        expiresAt: $existing->expiresAt(),
      );
    }

    // Create new trusted device
    $deviceId = $this->uuidFactory->create(TrustedDeviceId::class);

    $device = TrustedDevice::trust(
      id: $deviceId,
      userId: $command->userId,
      fingerprint: $fingerprint,
      ttlDays: $command->ttlDays,
    );

    // Get token before saving (plain token only available at creation)
    $plainToken = $device->token()->plain();

    $this->repository->save(device: $device);

    return new TrustDeviceResult(
      deviceId: $deviceId->value,
      token: $plainToken,
      deviceName: $device->name(),
      expiresAt: $device->expiresAt(),
    );
  }
  //#endregion
}
