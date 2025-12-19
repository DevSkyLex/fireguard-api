<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use OAuth\Domain\ValueObject\DPoPProof;

/**
 * Interface DPoPValidatorPort.
 *
 * Port for validating DPoP (Demonstrating Proof of Possession) proofs (RFC 9449).
 * DPoP is a mechanism for sender-constraining OAuth tokens.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface DPoPValidatorPort
{
    // #region Methods
    /**
     * Method validateProof.
     *
     * Validates a DPoP proof JWT.
     *
     * @since 1.0.0
     *
     * @param string      $dpopHeader    the DPoP header value (JWT)
     * @param string      $httpMethod    the HTTP method of the request
     * @param string      $httpUri       the HTTP URI of the request
     * @param string|null $expectedNonce expected server nonce (optional)
     * @param string|null $accessToken   access token for binding verification (optional)
     *
     * @return DPoPProof|null the validated proof or null if invalid
     */
    public function validateProof(
        string $dpopHeader,
        string $httpMethod,
        string $httpUri,
        ?string $expectedNonce = null,
        ?string $accessToken = null,
    ): ?DPoPProof;

    /**
     * Method generateNonce.
     *
     * Generates a new server nonce for DPoP.
     *
     * @since 1.0.0
     *
     * @return string the generated nonce
     */
    public function generateNonce(): string;

    /**
     * Method isNonceValid.
     *
     * Checks if a nonce is still valid.
     *
     * @since 1.0.0
     *
     * @param string $nonce the nonce to validate
     *
     * @return bool true if valid, false otherwise
     */
    public function isNonceValid(string $nonce): bool;

    /**
     * Method calculateThumbprint.
     *
     * Calculates the JWK thumbprint from a DPoP proof.
     *
     * @since 1.0.0
     *
     * @param string $dpopHeader the DPoP header value (JWT)
     *
     * @return string|null the JWK thumbprint or null if invalid
     */
    public function calculateThumbprint(string $dpopHeader): ?string;
    // #endregion
}
