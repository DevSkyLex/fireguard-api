<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Federation;

use Auth\Application\Contract\Federation\{FederatedConnection, ProviderId};

/**
 * Interface FederatedIdentityRepositoryPort.
 *
 * Persists external identities in the auth database.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedIdentityRepositoryPort
{
  // #region Methods
  /**
   * Method hasIssuerConflict
   *
   * @access public
   *
   * @param ProviderId $provider configured provider namespace
   * @param string $issuer verified issuer to pin
   *
   * @return bool whether a retained identity pins that namespace to another issuer
   */
  public function hasIssuerConflict(ProviderId $provider, string $issuer): bool;

  /**
   * Method findBySubject.
   *
   * Finds the external identity associated with a provider subject.
   *
   * @access public
   *
   * @param ProviderId $provider the identity provider
   * @param string $subject the provider-issued subject identifier
   *
   * @return ?FederatedConnection the matching federated identity
   */
  public function findBySubject(ProviderId $provider, string $subject): ?FederatedConnection;

  /**
   * Method findForUserProvider.
   *
   * Finds the user's federated connection for one provider.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   * @param ProviderId $provider the identity provider
   *
   * @return ?FederatedConnection the matching connection
   */
  public function findForUserProvider(string $userId, ProviderId $provider): ?FederatedConnection;

  /**
   * @return list<FederatedConnection>
   */
  public function findForUser(string $userId): array;

  /**
   * Method save.
   *
   * Persists a federated connection in the auth store.
   *
   * @access public
   *
   * @param FederatedConnection $connection the connection to persist
   *
   * @return void no return value
   */
  public function save(FederatedConnection $connection): void;

  /**
   * Method remove.
   *
   * Removes the supplied federated connection.
   *
   * @access public
   *
   * @param FederatedConnection $connection the connection to remove
   *
   * @return void no return value
   */
  public function remove(FederatedConnection $connection): void;

  /**
   * Method removePreservingAccess.
   *
   * Removes a provider connection while applying the password-access safeguard.
   *
   * @access public
   *
   * @param ProviderId $provider the identity provider
   * @param string $userId the local user identifier
   * @param bool $passwordConfigured whether the user has a configured password
   *
   * @return bool whether a connection was removed
   */
  public function removePreservingAccess(ProviderId $provider, string $userId, bool $passwordConfigured): bool;
  // #endregion
}
