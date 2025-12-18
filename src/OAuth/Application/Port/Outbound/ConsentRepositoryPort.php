<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\Model\Consent;
use OAuth\Domain\ValueObject\ConsentId;

/**
 * Interface ConsentRepositoryPort
 *
 * Port for Consent persistence.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ConsentRepositoryPort
{
  //#region Methods
  /**
   * Method save
   *
   * Saves a consent.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Consent $consent The consent to save.
   *
   * @return void
   */
  public function save(Consent $consent): void;

  /**
   * Method findById
   *
   * Finds a consent by ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ConsentId $id The consent ID.
   *
   * @return Consent|null The consent or null.
   */
  public function findById(ConsentId $id): ?Consent;

  /**
   * Method findByUserAndClient
   *
   * Finds active consent for a user-client pair.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string $clientId The client ID.
   *
   * @return Consent|null The consent or null.
   */
  public function findByUserAndClient(string $userId, string $clientId): ?Consent;

  /**
   * Method findAllByUser
   *
   * Finds all consents for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   *
   * @return list<Consent> The consents.
   */
  public function findAllByUser(string $userId): array;

  /**
   * Method revokeAllForUser
   *
   * Revokes all consents for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   *
   * @return int The number of consents revoked.
   */
  public function revokeAllForUser(string $userId): int;
  //#endregion
}
