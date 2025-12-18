<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use OAuth\Domain\ValueObject\DPoPProof;

/**
 * Interface DPoPValidatorPort
 *
 * Port for validating DPoP (Demonstrating Proof of Possession) proofs (RFC 9449).
 * DPoP is a mechanism for sender-constraining OAuth tokens.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface DPoPValidatorPort
{
  //#region Methods
  /**
   * Method validateProof
   *
   * Validates a DPoP proof JWT.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $dpopHeader The DPoP header value (JWT).
   * @param string $httpMethod The HTTP method of the request.
   * @param string $httpUri The HTTP URI of the request.
   * @param string|null $expectedNonce Expected server nonce (optional).
   * @param string|null $accessToken Access token for binding verification (optional).
   *
   * @return DPoPProof|null The validated proof or null if invalid.
   */
  public function validateProof(
    string $dpopHeader,
    string $httpMethod,
    string $httpUri,
    ?string $expectedNonce = null,
    ?string $accessToken = null,
  ): ?DPoPProof;

  /**
   * Method generateNonce
   *
   * Generates a new server nonce for DPoP.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The generated nonce.
   */
  public function generateNonce(): string;

  /**
   * Method isNonceValid
   *
   * Checks if a nonce is still valid.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $nonce The nonce to validate.
   *
   * @return bool True if valid, false otherwise.
   */
  public function isNonceValid(string $nonce): bool;

  /**
   * Method calculateThumbprint
   *
   * Calculates the JWK thumbprint from a DPoP proof.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $dpopHeader The DPoP header value (JWT).
   *
   * @return string|null The JWK thumbprint or null if invalid.
   */
  public function calculateThumbprint(string $dpopHeader): ?string;
  //#endregion
}
