<?php

declare(strict_types=1);

namespace TrustedDevice\Application\Port\Outbound;

use TrustedDevice\Domain\Model\TrustedDevice;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;

/**
 * Port TrustedDeviceRepositoryPort
 *
 * Outbound port for TrustedDevice 
 * persistence.
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
  /**
   * Method save
   *
   * Saves a trusted device.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TrustedDevice $device The device to save.
   *
   * @return void No return value.
   */
  public function save(TrustedDevice $device): void;

  /**
   * Method findById
   *
   * Finds a trusted device by its ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TrustedDeviceId $id The ID of the device to find.
   *
   * @return ?TrustedDevice The found device, or null if not found.
   */
  public function findById(TrustedDeviceId $id): ?TrustedDevice;

  /**
   * Method findByUserIdAndFingerprint
   *
   * Finds a trusted device by its user 
   * ID and fingerprint.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID of the device to find.
   * @param string $fingerprint The fingerprint of the device to find.
   *
   * @return ?TrustedDevice The found device, or null if not found.
   */
  public function findByUserIdAndFingerprint(string $userId, string $fingerprint): ?TrustedDevice;

  /**
   * Method findByToken
   *
   * Finds a trusted device by its token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tokenHash The token hash of the device to find.
   *
   * @return ?TrustedDevice The found device, or null if not found.
   */
  public function findByToken(string $tokenHash): ?TrustedDevice;

  /**
   * Method findAllByUserId
   *
   * Finds all trusted devices by their user ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID of the devices to find.
   *
   * @return list<TrustedDevice> The found devices.
   */
  public function findAllByUserId(string $userId): array;

  /**
   * Method revokeAllForUser
   *
   * Revokes all trusted devices for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID of the devices to revoke.
   *
   * @return int The number of devices revoked.
   */
  public function revokeAllForUser(string $userId): int;

  /**
   * Method delete
   *
   * Deletes a trusted device by its ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TrustedDeviceId $id The ID of the device to delete.
   *
   * @return void No return value.
   */
  public function delete(TrustedDeviceId $id): void;
  //#endregion
}
