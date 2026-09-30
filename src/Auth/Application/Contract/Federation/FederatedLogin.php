<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use Auth\Application\UseCase\Command\Session\Login\LoginResult;

/**
 * Contract FederatedLogin.
 *
 * Completed Fireguard login plus its validated local destination.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedLogin
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Pairs the completed login result with its return URL and account-creation outcome.
   *
   * @access public
   *
   * @param LoginResult $login authentication result produced for the federated identity
   * @param string $returnUrl validated application URL to use after login
   * @param bool $newAccount whether this login created a FireGuard account
   *
   * @return void
   */
  public function __construct(
    public LoginResult $login,
    public string $returnUrl,
    public bool $newAccount,
  ) {
  }
  // #endregion
}
