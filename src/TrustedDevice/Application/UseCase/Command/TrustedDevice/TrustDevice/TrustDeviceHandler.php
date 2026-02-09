<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice;

use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use TrustedDevice\Application\Port\Outbound\TrustedDeviceRepositoryPort;
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, TrustedDeviceId};

/**
 * Handler TrustDeviceHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustDeviceHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the handler with the
   * device repository and UUID factory.
   *
   * @since 1.0.0
   *
   * @param TrustedDeviceRepositoryPort $repository the device repository
   * @param UuidFactory $uuidFactory the UUID factory
   */
  public function __construct(
    private readonly TrustedDeviceRepositoryPort $repository,
    private readonly UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the trust device command.
   *
   * @since 1.0.0
   *
   * @param TrustDeviceCommand $command the command
   *
   * @return TrustDeviceResult the result
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

    // Always create a new device (to generate a fresh token)
    if (null !== $existing) {
      // Delete the old device to avoid unique constraint violation
      $this->repository->delete(id: $existing->id());
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
  // #endregion
}
