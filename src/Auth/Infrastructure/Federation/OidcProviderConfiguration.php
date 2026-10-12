<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Federation;

use Auth\Application\Contract\Federation\ProviderId;
use InvalidArgumentException;

use function is_array;
use function is_string;
use function parse_url;
use function strlen;
use function trim;

/**
 * Class OidcProviderConfiguration
 *
 * Server-owned confidential OIDC client configuration. No browser field selects endpoints.
 *
 * @category Configuration
 */
final readonly class OidcProviderConfiguration
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ProviderId $id immutable provider namespace
   * @param string $label public display label
   * @param bool $enabled current availability
   * @param string $issuer exact expected HTTPS issuer
   * @param string $authorizationEndpoint server-configured HTTPS authorization endpoint
   * @param string $tokenEndpoint server-configured HTTPS token endpoint
   * @param string $jwksUri server-configured HTTPS signing-key endpoint
   * @param ?string $userinfoEndpoint optional server-configured HTTPS UserInfo endpoint
   * @param string $clientId confidential client identifier
   * @param string $clientSecret confidential client secret
   *
   * @return void
   */
  public function __construct(
    public ProviderId $id,
    public string $label,
    public bool $enabled,
    public string $issuer,
    public string $authorizationEndpoint,
    public string $tokenEndpoint,
    public string $jwksUri,
    public ?string $userinfoEndpoint,
    public string $clientId,
    public string $clientSecret,
  ) {
    if (!$enabled) {
      return;
    }
    foreach ([$issuer, $authorizationEndpoint, $tokenEndpoint, $jwksUri, $userinfoEndpoint] as $url) {
      if (null === $url) {
        continue;
      }
      $parts = parse_url($url);
      if (!is_array($parts) || 'https' !== ($parts['scheme'] ?? null) || '' === ($parts['host'] ?? '') || isset($parts['user']) || isset($parts['fragment'])) {
        throw new InvalidArgumentException('OIDC providers require server-configured HTTPS endpoints.');
      }
    }
    $issuerParts = parse_url($issuer);
    if (strlen($issuer) > 500 || !is_array($issuerParts) || isset($issuerParts['query']) || '' === trim($clientId) || '' === trim($clientSecret)) {
      throw new InvalidArgumentException('OIDC providers require a stable issuer and confidential client credentials.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray
   *
   * @access public
   *
   * @param string $id configured provider key
   * @param array<string, mixed> $configuration server configuration
   *
   * @return self validated configuration
   */
  public static function fromArray(string $id, array $configuration): self
  {
    $string = static fn (string $key): string => is_string($configuration[$key] ?? null) ? $configuration[$key] : '';

    return new self(
      ProviderId::from($id),
      $string('label') ?: $id,
      true === ($configuration['enabled'] ?? false),
      $string('issuer'),
      $string('authorization_endpoint'),
      $string('token_endpoint'),
      $string('jwks_uri'),
      $string('userinfo_endpoint') ?: null,
      $string('client_id'),
      $string('client_secret'),
    );
  }
  // #endregion
}
