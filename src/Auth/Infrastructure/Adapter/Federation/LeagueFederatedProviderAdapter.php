<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Federation;

use Auth\Application\Contract\Federation\{FederatedAuthorization, FederatedProfile};
use Auth\Application\Port\Outbound\Federation\FederatedProviderClientPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function filter_var;
use function is_string;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * Adapter LeagueFederatedProviderAdapter.
 *
 * Wraps the League OAuth client for Google and the Microsoft common OIDC
 * endpoints. Provider tokens are kept only for the current request.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LeagueFederatedProviderAdapter implements FederatedProviderClientPort
{
  public function __construct(
    #[Autowire('%env(bool:GOOGLE_OIDC_ENABLED)%')]
    private bool $googleEnabled,
    #[Autowire('%env(GOOGLE_OIDC_CLIENT_ID)%')]
    private string $googleClientId,
    #[Autowire('%env(GOOGLE_OIDC_CLIENT_SECRET)%')]
    private string $googleClientSecret,
    #[Autowire('%env(bool:MICROSOFT_OIDC_ENABLED)%')]
    private bool $microsoftEnabled,
    #[Autowire('%env(MICROSOFT_OIDC_CLIENT_ID)%')]
    private string $microsoftClientId,
    #[Autowire('%env(MICROSOFT_OIDC_CLIENT_SECRET)%')]
    private string $microsoftClientSecret,
  ) {
  }

  public function isEnabled(FederatedProvider $provider): bool
  {
    return match ($provider) {
      FederatedProvider::GOOGLE => $this->googleEnabled
        && '' !== trim($this->googleClientId)
        && '' !== trim($this->googleClientSecret),
      FederatedProvider::MICROSOFT => $this->microsoftEnabled
        && '' !== trim($this->microsoftClientId)
        && '' !== trim($this->microsoftClientSecret),
    };
  }

  public function start(
    FederatedProvider $provider,
    string $redirectUri,
    string $state,
  ): FederatedAuthorization {
    $client = $this->client($provider, $redirectUri);
    $authorizationUrl = $client->getAuthorizationUrl([
      'state' => $state,
      'scope' => ['openid', 'profile', 'email'],
      'prompt' => 'select_account',
    ]);
    $codeVerifier = $client->getPkceCode();

    if (!is_string($codeVerifier) || '' === $codeVerifier) {
      throw new RuntimeException('The identity provider did not create a PKCE verifier.');
    }

    return new FederatedAuthorization($authorizationUrl, $codeVerifier);
  }

  public function complete(
    FederatedProvider $provider,
    string $redirectUri,
    string $code,
    string $codeVerifier,
  ): FederatedProfile {
    $client = $this->client($provider, $redirectUri);
    $client->setPkceCode($codeVerifier);
    $token = $client->getAccessToken('authorization_code', ['code' => $code]);
    if (!$token instanceof AccessToken) {
      throw new RuntimeException('The identity provider returned an unsupported access token.');
    }
    /** @var array<string, mixed> $claims */
    $claims = $client->getResourceOwner($token)->toArray();

    $subject = $this->claim($claims, 'sub') ?: $this->claim($claims, 'id');
    $email = $this->claim($claims, 'email');
    if ('' === $subject) {
      throw new FederatedAuthException('identity_missing', 'The identity provider did not return a stable identity.');
    }
    if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new FederatedAuthException('email_missing', 'The identity provider did not return a usable email address.');
    }

    $verifiedClaim = $claims['email_verified'] ?? $claims['verified_email'] ?? null;
    $verified = FederatedProvider::MICROSOFT === $provider
      || true === $verifiedClaim
      || (is_string($verifiedClaim) && 'true' === $verifiedClaim);

    return new FederatedProfile(
      provider: $provider,
      subject: $subject,
      email: $email,
      emailVerified: $verified,
      firstName: $this->claim($claims, 'given_name'),
      lastName: $this->claim($claims, 'family_name'),
    );
  }

  private function client(FederatedProvider $provider, string $redirectUri): AbstractProvider
  {
    if (!$this->isEnabled($provider)) {
      throw new RuntimeException('The requested identity provider is unavailable.');
    }

    return match ($provider) {
      FederatedProvider::GOOGLE => new GooglePkceProviderAdapter([
        'clientId' => $this->googleClientId,
        'clientSecret' => $this->googleClientSecret,
        'redirectUri' => $redirectUri,
        'scopes' => ['openid', 'profile', 'email'],
      ], ['httpClient' => new Client(['connect_timeout' => 5.0, 'timeout' => 10.0])]),
      FederatedProvider::MICROSOFT => new OidcPkceProviderAdapter([
        'clientId' => $this->microsoftClientId,
        'clientSecret' => $this->microsoftClientSecret,
        'redirectUri' => $redirectUri,
        'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
        'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        'urlResourceOwnerDetails' => 'https://graph.microsoft.com/oidc/userinfo',
        'scopes' => ['openid', 'profile', 'email'],
        'scopeSeparator' => ' ',
        'pkceMethod' => AbstractProvider::PKCE_METHOD_S256,
      ], ['httpClient' => new Client(['connect_timeout' => 5.0, 'timeout' => 10.0])]),
    };
  }

  /**
   * @param array<string, mixed> $claims
   */
  private function claim(array $claims, string $key): string
  {
    $value = $claims[$key] ?? '';

    return is_string($value) ? trim($value) : '';
  }
}
