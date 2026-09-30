<?php

declare(strict_types=1);

namespace User\Application\Contract\Federation;

/**
 * Contract FederatedUser.
 *
 * Safe user information exposed to the Auth module for federated sign-in.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedUser
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Projects the federated account state returned to the authentication flow.
   *
   * @access public
   *
   * @param string $userId user account identifier
   * @param string $email current account email address
   * @param bool $canLogin whether the account may sign in
   * @param bool $passwordConfigured whether a password credential is configured
   * @param bool $created whether this flow created the account
   * @param ?string $lastSignInMethod optional method used for the last sign-in
   *
   * @return void
   */
  public function __construct(
    public string $userId,
    public string $email,
    public bool $canLogin,
    public bool $passwordConfigured,
    public bool $created = false,
    public ?string $lastSignInMethod = null,
  ) {
  }
  // #endregion
}
