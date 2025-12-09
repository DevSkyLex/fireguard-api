<?php

declare(strict_types=1);

namespace TrustedDevice\Application\Port\Outbound;

use TrustedDevice\Domain\Model\TrustedDevice;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;

/**
 * Port TrustedDeviceRepositoryPort
 *
 * Outbound port for TrustedDevice persistence.
 *
 * @category Port
 * @package TrustedDevice\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TrustedDeviceRepositoryPort
{
  //#region Methods
  public function save(TrustedDevice $device): void;

  public function findById(TrustedDeviceId $id): ?TrustedDevice;

  public function findByUserIdAndFingerprint(string $userId, string $fingerprint): ?TrustedDevice;

  public function findByToken(string $tokenHash): ?TrustedDevice;

  /**
   * @return list<TrustedDevice>
   */
  public function findAllByUserId(string $userId): array;

  public function revokeAllForUser(string $userId): int;

  public function delete(TrustedDeviceId $id): void;
  //#endregion
}
