<?php

declare(strict_types=1);

namespace User\Application\Contract\Federation;

/**
 * Contract FederatedUser.
 *
 * Safe user information exposed to the Auth module for federated sign-in.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedUser
{
  public function __construct(
    public string $userId,
    public string $email,
    public bool $canLogin,
    public bool $passwordConfigured,
    public bool $created = false,
    public ?string $lastSignInMethod = null,
  ) {
  }
}
