<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Domain\ValueObject\TokenClaims;

/**
 * Port TokenSignerPort
 *
 * Provides token encoding/signing services
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TokenSignerPort
{
  //#region Methods
  /**
   * Method sign
   * @method sign(): string
   *
   * Signs the given payload and returns
   * an opaque token string.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TokenClaims $claims The claims to sign.
   *
   * @return string The signed token.
   */
  public function sign(TokenClaims $claims): string;

  /**
   * Method verify
   * @method verify(): array<string, mixed>
   *
   * Verifies and decodes a token, returning
   * its claims when valid.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The token to verify.
   *
   * @return TokenClaims The claims of the token.
   */
  public function verify(string $token): TokenClaims;
  //#endregion
}
