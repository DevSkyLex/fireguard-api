<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Token;

/**
 * Enum SignInGrantType.
 *
 * Identifies the primary authentication method that opened a Fireguard
 * session, including flows that still have to complete MFA.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum SignInGrantType: string
{
  /**
   * Case PASSWORD
   */
  case PASSWORD = 'password';

  /**
   * Case GOOGLE
   */
  case GOOGLE = 'federated_google';

  /**
   * Case MICROSOFT
   */
  case MICROSOFT = 'federated_microsoft';

  /**
   * Case OIDC
   */
  case OIDC = 'federated_oidc';

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
      self::OIDC => 'oidc',
    };
  }
}
