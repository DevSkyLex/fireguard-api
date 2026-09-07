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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedLogin
{
  public function __construct(
    public LoginResult $login,
    public string $returnUrl,
    public bool $newAccount,
  ) {
  }
}
