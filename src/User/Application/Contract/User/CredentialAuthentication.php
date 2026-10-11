<?php

declare(strict_types=1);

namespace User\Application\Contract\User;

/**
 * Account-owned password validation outcome.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CredentialAuthentication
{
  /**
   * @param string $status authenticated, invalid_password, unavailable or missing
   * @param UserView|null $user the authenticated account projection
   */
  public function __construct(public string $status, public ?UserView $user = null)
  {
  }
}
