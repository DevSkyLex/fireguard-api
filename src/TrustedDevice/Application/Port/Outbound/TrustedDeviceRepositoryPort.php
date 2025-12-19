<?php

declare(strict_types=1);

namespace TrustedDevice\Application\Port\Outbound;

use TrustedDevice\Domain\Model\TrustedDevice;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;

/**
 * Port TrustedDeviceRepositoryPort.
 *
 * Outbound port for TrustedDevice
 * persistence.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TrustedDeviceRepositoryPort
{
    // #region Methods
    /**
     * Method save.
     *
     * Saves a trusted device.
     *
     * @since 1.0.0
     *
     * @param TrustedDevice $device the device to save
     *
     * @return void no return value
     */
    public function save(TrustedDevice $device): void;

    /**
     * Method findById.
     *
     * Finds a trusted device by its ID.
     *
     * @since 1.0.0
     *
     * @param TrustedDeviceId $id the ID of the device to find
     *
     * @return ?TrustedDevice the found device, or null if not found
     */
    public function findById(TrustedDeviceId $id): ?TrustedDevice;

    /**
     * Method findByUserIdAndFingerprint.
     *
     * Finds a trusted device by its user
     * ID and fingerprint.
     *
     * @since 1.0.0
     *
     * @param string $userId      the user ID of the device to find
     * @param string $fingerprint the fingerprint of the device to find
     *
     * @return ?TrustedDevice the found device, or null if not found
     */
    public function findByUserIdAndFingerprint(string $userId, string $fingerprint): ?TrustedDevice;

    /**
     * Method findByToken.
     *
     * Finds a trusted device by its token.
     *
     * @since 1.0.0
     *
     * @param string $tokenHash the token hash of the device to find
     *
     * @return ?TrustedDevice the found device, or null if not found
     */
    public function findByToken(string $tokenHash): ?TrustedDevice;

    /**
     * Method findAllByUserId.
     *
     * Finds all trusted devices by their user ID.
     *
     * @since 1.0.0
     *
     * @param string $userId the user ID of the devices to find
     *
     * @return list<TrustedDevice> the found devices
     */
    public function findAllByUserId(string $userId): array;

    /**
     * Method revokeAllForUser.
     *
     * Revokes all trusted devices for a user.
     *
     * @since 1.0.0
     *
     * @param string $userId the user ID of the devices to revoke
     *
     * @return int the number of devices revoked
     */
    public function revokeAllForUser(string $userId): int;

    /**
     * Method delete.
     *
     * Deletes a trusted device by its ID.
     *
     * @since 1.0.0
     *
     * @param TrustedDeviceId $id the ID of the device to delete
     *
     * @return void no return value
     */
    public function delete(TrustedDeviceId $id): void;
    // #endregion
}
