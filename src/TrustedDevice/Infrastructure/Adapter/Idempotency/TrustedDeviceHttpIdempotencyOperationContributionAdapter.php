<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeAllDevices\{RevokeAllDevicesCommand, RevokeAllDevicesResult};
use TrustedDevice\Application\UseCase\Command\TrustedDevice\RevokeDevice\{RevokeDeviceCommand, RevokeDeviceResult};
use TrustedDevice\Application\UseCase\Command\TrustedDevice\TrustDevice\{TrustDeviceCommand, TrustDeviceResult};

/**
 * Class TrustedDeviceHttpIdempotencyOperationContributionAdapter
 *
 * Declares explicit owner transactions, result schemas and mandatory keys for every published HTTP mutation.
 *
 * @category Adapter
 */
final readonly class TrustedDeviceHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'trusted_device_trust',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: TrustDeviceCommand::class,
        resultClass: TrustDeviceResult::class,
        statusCode: 201,
        secret: true,
        requiredKey: true,
        resultIdentifierProperty: 'deviceId',
        resourceKind: 'trusted_device',
      ),
      new HttpIdempotencyOperation(
        name: 'trusted_device_revoke',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: RevokeDeviceCommand::class,
        resultClass: RevokeDeviceResult::class,
        statusCode: 204,
        requiredKey: true,
        resultIdentifierProperty: 'deviceId',
        resourceKind: 'trusted_device',
      ),
      new HttpIdempotencyOperation(
        name: 'trusted_device_revoke_all',
        database: 'auth',
        transactionMode: 'bus_owner',
        primaryCommand: RevokeAllDevicesCommand::class,
        resultClass: RevokeAllDevicesResult::class,
        statusCode: 201,
        requiredKey: true,
      ),
    ];
  }
  // #endregion
}
