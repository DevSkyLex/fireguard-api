<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

/**
 * Interface IdTokenIssuerPort.
 *
 * Port for issuing OpenID Connect ID tokens.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface IdTokenIssuerPort
{
  /**
   * Issues an OpenID Connect ID token.
   *
   * @param non-empty-string $subject the subject (user identifier)
   * @param non-empty-string $audience the audience (client identifier)
   * @param string|null $nonce the OIDC nonce (optional)
   * @param array<string, mixed> $claims additional claims (optional)
   *
   * @return string the signed ID token
   */
  public function issueIdToken(string $subject, string $audience, ?string $nonce = null, array $claims = []): string;
}
