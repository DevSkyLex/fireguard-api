<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\Model\Consent;
use OAuth\Domain\ValueObject\ConsentId;

/**
 * Interface ConsentRepositoryPort.
 *
 * Port for Consent persistence.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ConsentRepositoryPort
{
    // #region Methods
    /**
     * Method save.
     *
     * Saves a consent.
     *
     * @since 1.0.0
     *
     * @param Consent $consent the consent to save
     */
    public function save(Consent $consent): void;

    /**
     * Method findById.
     *
     * Finds a consent by ID.
     *
     * @since 1.0.0
     *
     * @param ConsentId $id the consent ID
     *
     * @return Consent|null the consent or null
     */
    public function findById(ConsentId $id): ?Consent;

    /**
     * Method findByUserAndClient.
     *
     * Finds active consent for a user-client pair.
     *
     * @since 1.0.0
     *
     * @param string $userId   the user ID
     * @param string $clientId the client ID
     *
     * @return Consent|null the consent or null
     */
    public function findByUserAndClient(string $userId, string $clientId): ?Consent;

    /**
     * Method findAllByUser.
     *
     * Finds all consents for a user.
     *
     * @since 1.0.0
     *
     * @param string $userId the user ID
     *
     * @return list<Consent> the consents
     */
    public function findAllByUser(string $userId): array;

    /**
     * Method revokeAllForUser.
     *
     * Revokes all consents for a user.
     *
     * @since 1.0.0
     *
     * @param string $userId the user ID
     *
     * @return int the number of consents revoked
     */
    public function revokeAllForUser(string $userId): int;
    // #endregion
}
