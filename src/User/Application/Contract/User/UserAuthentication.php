<?php

declare(strict_types=1);

namespace User\Application\Contract\User;

/**
 * Credential verification without password material or mutable account state.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserAuthentication
{
  public function __construct(public bool $authenticated, public ?string $userId = null, public ?string $email = null)
  {
  }
}
