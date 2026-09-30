<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Federation;

use Auth\Application\Contract\Federation\{FederatedAuthorization, FederatedProfile};
use Auth\Application\Port\Outbound\Federation\FederatedProviderClientPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Domain\ValueObject\Federation\FederatedProvider;
use Auth\Infrastructure\Exception\FederatedProviderFailureException;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function filter_var;
use function is_string;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * Class LeagueFederatedProviderAdapter
 *
 * Wraps the League OAuth client for Google and the Microsoft common OIDC
 * endpoints. Provider tokens are kept only for the current request.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LeagueFederatedProviderAdapter implements FederatedProviderClientPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the enablement flags and credentials used to configure Google and Microsoft OIDC clients.
   *
   * @access public
   *
   * @param bool $googleEnabled whether Google sign-in is enabled
   * @param string $googleClientId the Google OAuth client identifier
   * @param string $googleClientSecret the Google OAuth client secret
   * @param bool $microsoftEnabled whether Microsoft sign-in is enabled
   * @param string $microsoftClientId the Microsoft OAuth client identifier
   * @param string $microsoftClientSecret the Microsoft OAuth client secret
   *
   * @return void
   */
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
  // #endregion

  // #region Methods
  /**
   * Method isEnabled
   *
   * Reports a provider as usable only when its feature flag and both credentials are configured.
   *
   * @access public
   *
   * @param FederatedProvider $provider the identity provider to check
   *
   * @return bool whether the provider has complete enabled configuration
   */
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

  /**
   * Method start
   *
   * Builds the provider authorization URL and returns the PKCE verifier required to complete the same flow.
   *
   * @access public
   *
   * @param FederatedProvider $provider the identity provider
   * @param string $redirectUri the registered callback URI for this flow
   * @param string $state the caller-generated CSRF state value
   *
   * @return FederatedAuthorization the authorization URL and PKCE verifier
   *
   * @throws FederatedProviderFailureException when the provider is disabled or cannot create a verifier
   */
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
      throw new FederatedProviderFailureException('The identity provider did not create a PKCE verifier.');
    }

    return new FederatedAuthorization($authorizationUrl, $codeVerifier);
  }

  /**
   * Method complete
   *
   * Exchanges the authorization code with its PKCE verifier and maps verified provider claims to a local profile.
   *
   * @access public
   *
   * @param FederatedProvider $provider the identity provider used for authorization
   * @param string $redirectUri the callback URI used to obtain the authorization code
   * @param string $code the provider-issued authorization code
   * @param string $codeVerifier the PKCE verifier returned by start
   *
   * @return FederatedProfile the stable external identity and profile details
   *
   * @throws FederatedProviderFailureException when the provider returns an unsupported token
   * @throws FederatedAuthException when the provider identity or email is unusable
   */
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
      throw new FederatedProviderFailureException('The identity provider returned an unsupported access token.');
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

  /**
   * Method client
   *
   * Creates the provider-specific PKCE client with bounded HTTP timeouts and the callback URI for this flow.
   *
   * @access private
   *
   * @param FederatedProvider $provider the provider client to create
   * @param string $redirectUri the callback URI registered for the authorization flow
   *
   * @return AbstractProvider the configured League OAuth client
   *
   * @throws FederatedProviderFailureException when the provider is not enabled
   */
  private function client(FederatedProvider $provider, string $redirectUri): AbstractProvider
  {
    if (!$this->isEnabled($provider)) {
      throw new FederatedProviderFailureException('The requested identity provider is unavailable.');
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
  // #endregion

  /**
   * Method claim
   *
   * Reads a non-empty string claim and trims whitespace before returning it.
   *
   * @access private
   *
   * @param array<string, mixed> $claims provider claims returned by the user-info endpoint
   * @param string $key claim name
   *
   * @return string trimmed claim value, or an empty string when absent or non-string
   */
  private function claim(array $claims, string $key): string
  {
    $value = $claims[$key] ?? '';

    return is_string($value) ? trim($value) : '';
  }
}
