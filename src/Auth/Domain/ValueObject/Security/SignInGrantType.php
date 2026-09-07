<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject\Security;

/**
 * Enum SignInGrantType.
 *
 * Identifies the primary authentication method that opened a Fireguard
 * session, including flows that still have to complete MFA.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum SignInGrantType: string
{
  case PASSWORD = 'password';

  case GOOGLE = 'federated_google';

  case MICROSOFT = 'federated_microsoft';

  /**
   * Returns the stable method exposed by account-security contracts.
   *
   * @since 1.0.0
   *
   * @return string the persisted sign-in method
   */
  public function method(): string
  {
    return match ($this) {
      self::PASSWORD => 'password',
      self::GOOGLE => 'google',
      self::MICROSOFT => 'microsoft',
    };
  }
}
