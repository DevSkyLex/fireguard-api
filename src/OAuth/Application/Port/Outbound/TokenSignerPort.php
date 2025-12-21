<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\ValueObject\TokenClaims;

/**
 * Port TokenSignerPort.
 *
 * Provides token encoding/signing services for OAuth tokens.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenSignerPort
{
  // #region Methods
  /**
   * Method sign.
   *
   * Signs the given payload and returns
   * an opaque token string.
   *
   * @since 1.0.0
   *
   * @param TokenClaims $claims the claims to sign
   *
   * @return string the signed token
   */
  public function sign(TokenClaims $claims): string;

  /**
   * Method verify.
   *
   * Verifies and decodes a token, returning
   * its claims when valid.
   *
   * @since 1.0.0
   *
   * @param string $token the token to verify
   *
   * @return TokenClaims the claims of the token
   */
  public function verify(string $token): TokenClaims;
  // #endregion
}
