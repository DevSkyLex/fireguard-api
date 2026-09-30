<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\Federation\FederatedUser;

/**
 * Interface FederatedUserPort.
 *
 * Published User-module boundary used by Auth for external identities.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedUserPort
{
  // #region Methods
  /**
   * Method find.
   *
   * Looks up a locally provisioned user by identifier.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   *
   * @return ?FederatedUser the user projection, when present
   */
  public function find(string $userId): ?FederatedUser;

  /**
   * Method provision.
   *
   * Creates a local user profile for a federated identity when required.
   *
   * @access public
   *
   * @param string $email the identity's email address
   * @param string $firstName the profile first name
   * @param string $lastName the profile last name
   *
   * @return ?FederatedUser the provisioned user, or null when not provisioned
   */
  public function provision(string $email, string $firstName, string $lastName): ?FederatedUser;

  /**
   * Method recordSuccessfulLogin.
   *
   * Records a successful login and returns the refreshed user projection.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   *
   * @return ?FederatedUser the updated user, when present
   */
  public function recordSuccessfulLogin(string $userId): ?FederatedUser;

  /**
   * Method recordSignInMethod.
   *
   * Stores the sign-in method used for the user's account.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   * @param string $method sign-in method identifier
   *
   * @return bool whether the method was recorded
   */
  public function recordSignInMethod(string $userId, string $method): bool;

  /**
   * Method setInitialPassword.
   *
   * Sets the initial password for a user who does not yet have one.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   * @param string $plainPassword the password supplied by the user
   *
   * @return bool whether the initial password was set
   */
  public function setInitialPassword(string $userId, string $plainPassword): bool;
  // #endregion
}
