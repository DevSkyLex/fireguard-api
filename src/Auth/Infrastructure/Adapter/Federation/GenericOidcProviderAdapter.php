<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Federation;

use Auth\Application\Contract\Federation\{FederatedAuthorization, FederatedProfile, FederatedProviderDescriptor, ProviderId};
use Auth\Application\Port\Outbound\Federation\FederatedProviderClientPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Federation\OidcProviderConfiguration;
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\{Parser, Plain};
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_values;
use function base64_decode;
use function base64_encode;
use function chr;
use function chunk_split;
use function count;
use function filter_var;
use function hash;
use function hash_equals;
use function http_build_query;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function ord;
use function preg_match;
use function random_bytes;
use function rtrim;
use function str_contains;
use function strlen;
use function strtr;
use function trim;

use const FILTER_VALIDATE_EMAIL;
use const PHP_QUERY_RFC3986;

/**
 * Class GenericOidcProviderAdapter
 *
 * Verifies a configured issuer's RS256 ID token before exposing any identity claim.
 * Token, key and optional UserInfo requests use only trusted server configuration.
 *
 * @category Adapter
 */
final readonly class GenericOidcProviderAdapter implements FederatedProviderClientPort
{
  // #region Properties
  /**
   * Property configurations
   *
   * @var array<string, OidcProviderConfiguration>
   */
  private array $configurations;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param HttpClientInterface $httpClient bounded external HTTP requests
   * @param array<string, array<string, mixed>> $providers server-owned provider configurations
   *
   * @return void
   */
  public function __construct(private HttpClientInterface $httpClient, array $providers)
  {
    $configurations = [];
    foreach ($providers as $id => $configuration) {
      if (in_array($id, ['google', 'microsoft'], true)) {
        throw new InvalidArgumentException('Generic OIDC registrations cannot replace legacy provider identifiers.');
      }
      $configurations[$id] = OidcProviderConfiguration::fromArray($id, $configuration);
    }
    $this->configurations = $configurations;
  }
  // #endregion

  // #region Methods
  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<FederatedProviderDescriptor> safe registered provider metadata
   */
  public function descriptors(): array
  {
    return array_values(array_map(
      static fn (OidcProviderConfiguration $configuration): FederatedProviderDescriptor => new FederatedProviderDescriptor($configuration->id, $configuration->label, $configuration->enabled),
      $this->configurations,
    ));
  }

  /**
   * Method isEnabled
   *
   * @access public
   *
   * @param ProviderId $provider registered provider identifier
   *
   * @return bool whether the configured provider is enabled
   */
  public function isEnabled(ProviderId $provider): bool
  {
    return $this->configurations[$provider->value]->enabled ?? false;
  }

  /**
   * Method start
   *
   * Generates fresh PKCE and nonce values; only the nonce hash is retained locally.
   *
   * @access public
   *
   * @param ProviderId $provider registered provider identifier
   * @param string $redirectUri server-derived callback URI
   * @param string $state server-generated browser-bound state
   *
   * @return FederatedAuthorization provider redirect, verifier and nonce hash
   */
  public function start(ProviderId $provider, string $redirectUri, string $state): FederatedAuthorization
  {
    $configuration = $this->configuration($provider);
    $verifier = $this->randomToken();
    $nonce = $this->randomToken();
    $query = http_build_query([
      'client_id' => $configuration->clientId,
      'response_type' => 'code',
      'redirect_uri' => $redirectUri,
      'scope' => 'openid profile email',
      'state' => $state,
      'nonce' => $nonce,
      'code_challenge' => $this->base64Url(hash('sha256', $verifier, true)),
      'code_challenge_method' => 'S256',
    ], '', '&', PHP_QUERY_RFC3986);

    return new FederatedAuthorization(
      $configuration->authorizationEndpoint . (str_contains($configuration->authorizationEndpoint, '?') ? '&' : '?') . $query,
      $verifier,
      hash('sha256', $nonce),
    );
  }

  /**
   * Method complete
   *
   * @access public
   *
   * @param ProviderId $provider registered provider identifier
   * @param string $redirectUri retained server-derived callback URI
   * @param string $code provider authorization code
   * @param string $codeVerifier retained PKCE verifier
   * @param ?string $nonceHash retained nonce hash, mandatory for generic OIDC
   *
   * @return FederatedProfile cryptographically verified external identity
   */
  public function complete(ProviderId $provider, string $redirectUri, string $code, string $codeVerifier, ?string $nonceHash = null): FederatedProfile
  {
    $configuration = $this->configuration($provider);
    if (null === $nonceHash || '' === $code || '' === $codeVerifier) {
      throw $this->invalidIdentity();
    }

    try {
      $response = $this->httpClient->request('POST', $configuration->tokenEndpoint, [
        'auth_basic' => [$configuration->clientId, $configuration->clientSecret],
        'body' => ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri, 'code_verifier' => $codeVerifier],
        'headers' => ['Accept' => 'application/json'],
        'timeout' => 10,
        'max_duration' => 10,
        'max_redirects' => 0,
      ])->toArray();
      $claims = $this->verifiedClaims($response, $configuration, $nonceHash);
      $subject = $this->stringClaim($claims, 'sub');
      if ('' === trim($subject) || strlen($subject) > 255) {
        throw $this->invalidIdentity();
      }
      $claims = $this->userinfoClaims($claims, $response, $configuration, $subject);
      $email = trim($this->stringClaim($claims, 'email'));
      if (strlen($email) > 320 || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new FederatedAuthException('email_missing', 'The identity provider did not return a usable email address.');
      }

      return new FederatedProfile($provider, $subject, $email, true === ($claims['email_verified'] ?? false), $this->stringClaim($claims, 'given_name'), $this->stringClaim($claims, 'family_name'), $configuration->issuer);
    } catch (FederatedAuthException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new FederatedAuthException('provider_failed', 'The sign-in provider could not verify this connection.', $exception);
    }
  }

  /**
   * Method verifiedClaims
   *
   * Verifies the configured signing key before validating or exposing any ID token claim.
   *
   * @access private
   *
   * @param array<array-key, mixed> $response the token endpoint response
   * @param OidcProviderConfiguration $configuration the server-owned issuer and signing key endpoint
   * @param string $nonceHash the retained browser flow nonce proof
   *
   * @return array<string, mixed> signature-verified and claim-validated identity data
   */
  private function verifiedClaims(array $response, OidcProviderConfiguration $configuration, string $nonceHash): array
  {
    $idToken = $response['id_token'] ?? null;
    if (!is_string($idToken) || '' === $idToken || strlen($idToken) > 65536) {
      throw $this->invalidIdentity();
    }
    $token = new Parser(new JoseEncoder())->parse($idToken);
    if (!$token instanceof Plain || 'RS256' !== $token->headers()->get('alg') || $token->headers()->has('crit') || false === $token->headers()->get('b64', true)) {
      throw $this->invalidIdentity();
    }
    $kid = $token->headers()->get('kid');
    if (!is_string($kid) || '' === $kid) {
      throw $this->invalidIdentity();
    }
    $pem = $this->verificationKey($configuration, $kid);
    if (!new Sha256()->verify($token->signature()->hash(), $token->payload(), InMemory::plainText($pem))) {
      throw $this->invalidIdentity();
    }
    $claims = $token->claims()->all();
    $this->validateClaims($claims, $configuration, $nonceHash);

    return $claims;
  }

  /**
   * Method userinfoClaims
   *
   * Merges optional UserInfo only after its subject matches the verified ID token, retaining mailbox proof ownership.
   *
   * @access private
   *
   * @param array<string, mixed> $claims the verified ID token claims
   * @param array<array-key, mixed> $response the token endpoint response
   * @param OidcProviderConfiguration $configuration the optional server-owned UserInfo endpoint
   * @param string $subject the verified non-empty subject
   *
   * @return array<array-key, mixed> verified identity claims with subject-matched UserInfo
   */
  private function userinfoClaims(array $claims, array $response, OidcProviderConfiguration $configuration, string $subject): array
  {
    if (null === $configuration->userinfoEndpoint) {
      return $claims;
    }
    $accessToken = $response['access_token'] ?? null;
    if (!is_string($accessToken) || '' === $accessToken) {
      throw $this->invalidIdentity();
    }
    $userinfo = $this->httpClient->request('GET', $configuration->userinfoEndpoint, [
      'auth_bearer' => $accessToken,
      'headers' => ['Accept' => 'application/json'],
      'timeout' => 10,
      'max_duration' => 10,
      'max_redirects' => 0,
    ])->toArray();
    if ($this->stringClaim($userinfo, 'sub') !== $subject) {
      throw $this->invalidIdentity();
    }
    if (array_key_exists('email', $userinfo) && $userinfo['email'] !== ($claims['email'] ?? null)) {
      // Verification belongs to the address; UserInfo must prove its replacement.
      $claims['email_verified'] = false;
    }
    if (!array_key_exists('email', $userinfo) && true === ($userinfo['email_verified'] ?? false)) {
      unset($userinfo['email_verified']);
    }

    return array_merge($claims, $userinfo);
  }

  /**
   * Method configuration
   *
   * @access private
   *
   * @param ProviderId $provider requested provider namespace
   *
   * @return OidcProviderConfiguration enabled server configuration
   */
  private function configuration(ProviderId $provider): OidcProviderConfiguration
  {
    if (!$this->isEnabled($provider)) {
      throw new FederatedAuthException('provider_unavailable', 'This sign-in provider is unavailable.');
    }

    return $this->configurations[$provider->value];
  }

  /**
   * Method validateClaims
   *
   * Called only after a configured signing key verifies the ID token signature.
   *
   * @access private
   *
   * @param array<string, mixed> $claims signed ID token claims
   * @param OidcProviderConfiguration $configuration trusted issuer and client
   * @param string $nonceHash retained nonce proof
   *
   * @return void
   */
  private function validateClaims(array $claims, OidcProviderConfiguration $configuration, string $nonceHash): void
  {
    $now = new DateTimeImmutable();
    $audience = $claims['aud'] ?? null;
    $expiresAt = $claims['exp'] ?? null;
    $issuedAt = $claims['iat'] ?? null;
    $notBefore = $claims['nbf'] ?? null;
    $nonce = $claims['nonce'] ?? null;
    if (
      $this->stringClaim($claims, 'iss') !== $configuration->issuer
      || !is_array($audience) || !in_array($configuration->clientId, $audience, true)
      || !$expiresAt instanceof DateTimeImmutable || $expiresAt <= $now
      || !$issuedAt instanceof DateTimeImmutable || $issuedAt > $now->modify('+60 seconds')
      || (null !== $notBefore && (!$notBefore instanceof DateTimeImmutable || $notBefore > $now))
      || (count($audience) > 1 && $this->stringClaim($claims, 'azp') !== $configuration->clientId)
      || (isset($claims['azp']) && $this->stringClaim($claims, 'azp') !== $configuration->clientId)
      || !is_string($nonce) || '' === $nonce || !hash_equals($nonceHash, hash('sha256', $nonce))
    ) {
      throw $this->invalidIdentity();
    }
  }

  /**
   * Method verificationKey
   *
   * Selects one RS256 signing key from the configured issuer's JWKS.
   * Token-supplied key URLs and certificates never select a network destination.
   *
   * @access private
   *
   * @param OidcProviderConfiguration $configuration trusted key endpoint
   * @param string $kid signed-token header key identifier
   *
   * @return non-empty-string RSA public-key PEM
   */
  private function verificationKey(OidcProviderConfiguration $configuration, string $kid): string
  {
    $jwks = $this->httpClient->request('GET', $configuration->jwksUri, ['timeout' => 10, 'max_duration' => 10, 'max_redirects' => 0])->toArray();
    $keys = $jwks['keys'] ?? null;
    if (!is_array($keys)) {
      throw $this->invalidIdentity();
    }
    $matches = array_values(array_filter($keys, static fn (mixed $key): bool => is_array($key) && ($key['kid'] ?? null) === $kid));
    if (1 !== count($matches)) {
      throw $this->invalidIdentity();
    }
    $key = $matches[0];
    if ('RSA' !== ($key['kty'] ?? null) || 'RS256' !== ($key['alg'] ?? 'RS256') || 'sig' !== ($key['use'] ?? 'sig') || (isset($key['key_ops']) && (!is_array($key['key_ops']) || !in_array('verify', $key['key_ops'], true)))) {
      throw $this->invalidIdentity();
    }
    $n = $key['n'] ?? null;
    $e = $key['e'] ?? null;
    if (!is_string($n) || !is_string($e)) {
      throw $this->invalidIdentity();
    }
    $der = $this->asn1Integer($this->decodeBase64Url($n)) . $this->asn1Integer($this->decodeBase64Url($e));
    $der = "\x30" . $this->asn1Length(strlen($der)) . $der;

    return "-----BEGIN RSA PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END RSA PUBLIC KEY-----\n";
  }

  /**
   * Method decodeBase64Url
   *
   * @access private
   *
   * @param string $value encoded RSA public integer
   *
   * @return string validated binary value
   */
  private function decodeBase64Url(string $value): string
  {
    if ('' === $value || strlen($value) > 2048 || 1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
      throw $this->invalidIdentity();
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if (false === $decoded || '' === $decoded) {
      throw $this->invalidIdentity();
    }

    return $decoded;
  }

  /**
   * Method asn1Integer
   *
   * @access private
   *
   * @param string $bytes unsigned RSA public integer
   *
   * @return string DER integer
   */
  private function asn1Integer(string $bytes): string
  {
    $bytes = ltrim($bytes, "\x00");
    if ('' === $bytes) {
      throw $this->invalidIdentity();
    }
    if (ord($bytes[0]) > 127) {
      $bytes = "\x00" . $bytes;
    }

    return "\x02" . $this->asn1Length(strlen($bytes)) . $bytes;
  }

  /**
   * Method asn1Length
   *
   * @access private
   *
   * @param int $length DER content length
   *
   * @return string DER encoded length
   */
  private function asn1Length(int $length): string
  {
    if ($length < 128) {
      return chr($length);
    }
    $bytes = '';
    while ($length > 0) {
      $bytes = chr($length & 255) . $bytes;
      $length >>= 8;
    }

    return chr(128 | strlen($bytes)) . $bytes;
  }

  /**
   * Method stringClaim
   *
   * @access private
   *
   * @param array<array-key, mixed> $claims signed or subject-matched provider claims
   * @param string $name claim name
   *
   * @return string exact string claim or empty value
   */
  private function stringClaim(array $claims, string $name): string
  {
    return is_string($claims[$name] ?? null) ? $claims[$name] : '';
  }

  /**
   * Method invalidIdentity
   *
   * @access private
   *
   * @return FederatedAuthException safe validation rejection
   */
  private function invalidIdentity(): FederatedAuthException
  {
    return new FederatedAuthException('invalid_identity', 'The identity provider returned an invalid identity.');
  }

  /**
   * Method randomToken
   *
   * @access private
   *
   * @return string URL-safe 256-bit random value
   */
  private function randomToken(): string
  {
    return $this->base64Url(random_bytes(32));
  }

  /**
   * Method base64Url
   *
   * @access private
   *
   * @param string $value binary value
   *
   * @return string unpadded URL-safe encoding
   */
  private function base64Url(string $value): string
  {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }
  // #endregion
}
