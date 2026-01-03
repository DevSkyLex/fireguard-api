<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\Discovery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use OAuth\Domain\ValueObject\Scope\DefaultScopes;
use OAuth\Presentation\Api\Dto\Output\Discovery\OpenIdConfigurationOutput;
use OAuth\Presentation\Api\Operation\DiscoveryOperations;
use OAuth\Presentation\Api\Operation\OAuthOperations;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_map;
use function ltrim;
use function rtrim;
use function str_starts_with;
use function trim;

/**
 * Provider OpenIdConfigurationProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OpenIdConfigurationOutput>
 */
final readonly class OpenIdConfigurationProvider implements ProviderInterface
{
  // #region Constants
  /**
   * Constant DEFAULT_TOKEN_PATH.
   *
   * The default token endpoint path.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_TOKEN_PATH = '/api/oauth2/token';

  /**
   * Constant DEFAULT_USERINFO_PATH.
   *
   * The default userinfo endpoint path.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_USERINFO_PATH = '/api/oauth2/userinfo';

  /**
   * Constant DEFAULT_AUTHORIZE_PATH.
   *
   * The default authorization endpoint path.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_AUTHORIZE_PATH = '/api/oauth2/authorize';

  /**
   * Constant DEFAULT_JWKS_PATH.
   *
   * The default JWKS endpoint path.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_JWKS_PATH = '/api/.well-known/jwks.json';

  /**
   * Constant DEFAULT_REVOCATION_PATH.
   *
   * The default revocation endpoint path.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_REVOCATION_PATH = '/api/oauth2/token/revoke';

  /**
   * Constant DEFAULT_INTROSPECTION_PATH.
   *
   * The default introspection endpoint path.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_INTROSPECTION_PATH = '/api/oauth2/token/introspect';

  /**
   * Constant DEFAULT_END_SESSION_PATH.
   *
   * The default end-session endpoint path.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_END_SESSION_PATH = '/api/oauth2/logout';
  // #endregion

  private readonly string $issuer;

  private readonly string $authorizePath;

  private readonly string $logoutPath;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * OpenIdConfigurationProvider class.
   *
   * @since 1.0.0
   *
   * @param RequestStack $requestStack the request stack
   * @param UrlGeneratorInterface $urlGenerator the URL generator
   * @param string $issuer the issuer URL
   * @param string $authorizePath the authorize endpoint path or route name
   * @param string $logoutPath the logout endpoint path or route name
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly UrlGeneratorInterface $urlGenerator,
    #[Autowire('%env(OAUTH_ISSUER)%')]
    ?string $issuer = null,
    #[Autowire('%env(default::OAUTH_AUTHORIZE_PATH)%')]
    ?string $authorizePath = null,
    #[Autowire('%env(default::OAUTH_LOGOUT_PATH)%')]
    ?string $logoutPath = null,
  ) {
    $this->issuer = $issuer ?? '';
    $this->authorizePath = $authorizePath ?? '';
    $this->logoutPath = $logoutPath ?? '';
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the OpenID Connect configuration.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return OpenIdConfigurationOutput the configuration
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OpenIdConfigurationOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    $requestBaseUrl = $request ? $request->getSchemeAndHttpHost() : '';
    $issuer = '' !== $this->issuer ? $this->issuer : $requestBaseUrl;
    $endpointBaseUrl = '' !== $issuer ? $issuer : $requestBaseUrl;

    $output = new OpenIdConfigurationOutput();

    // Issuer
    $output->issuer = $issuer;

    // Authorization endpoint
    $authorizationEndpoint = $this->resolveOptionalEndpoint(
      configuredValue: $this->authorizePath,
      baseUrl: $endpointBaseUrl,
    );
    if (null === $authorizationEndpoint) {
      $authorizationEndpoint = $this->generateEndpointUrl(
        routeName: OAuthOperations::AUTHORIZE,
        fallbackPath: self::DEFAULT_AUTHORIZE_PATH,
        baseUrl: $endpointBaseUrl,
      );
    }
    $output->authorizationEndpoint = $authorizationEndpoint;

    // Token endpoint
    $output->tokenEndpoint = $this->generateEndpointUrl(
      routeName: OAuthOperations::TOKEN,
      fallbackPath: self::DEFAULT_TOKEN_PATH,
      baseUrl: $endpointBaseUrl,
    );

    // Userinfo endpoint
    $output->userinfoEndpoint = $this->generateEndpointUrl(
      routeName: OAuthOperations::USERINFO,
      fallbackPath: self::DEFAULT_USERINFO_PATH,
      baseUrl: $endpointBaseUrl,
    );

    // JWKS endpoint
    $output->jwksUri = $this->generateEndpointUrl(
      routeName: DiscoveryOperations::JWKS,
      fallbackPath: self::DEFAULT_JWKS_PATH,
      baseUrl: $endpointBaseUrl,
    );

    // Revocation endpoint
    $output->revocationEndpoint = $this->generateEndpointUrl(
      routeName: OAuthOperations::REVOKE_TOKEN,
      fallbackPath: self::DEFAULT_REVOCATION_PATH,
      baseUrl: $endpointBaseUrl,
    );

    // Introspection endpoint
    $output->introspectionEndpoint = $this->generateEndpointUrl(
      routeName: OAuthOperations::INTROSPECT_TOKEN,
      fallbackPath: self::DEFAULT_INTROSPECTION_PATH,
      baseUrl: $endpointBaseUrl,
    );

    // End session endpoint
    $endSessionEndpoint = $this->resolveOptionalEndpoint(
      configuredValue: $this->logoutPath,
      baseUrl: $endpointBaseUrl,
    );
    if (null === $endSessionEndpoint) {
      $endSessionEndpoint = $this->generateEndpointUrl(
        routeName: OAuthOperations::END_SESSION,
        fallbackPath: self::DEFAULT_END_SESSION_PATH,
        baseUrl: $endpointBaseUrl,
      );
    }
    $output->endSessionEndpoint = $endSessionEndpoint;

    // Scopes supported
    $output->scopesSupported = array_map('strtolower', DefaultScopes::USER_SCOPES);

    // Response types supported
    $output->responseTypesSupported = ['code'];

    // Grant types supported
    $grantTypes = ['client_credentials', 'refresh_token', 'authorization_code'];
    $output->grantTypesSupported = $grantTypes;

    // Token endpoint auth methods supported
    $output->tokenEndpointAuthMethodsSupported = ['client_secret_post'];

    // Code challenge methods supported
    $output->codeChallengeMethodsSupported = ['S256', 'plain'];

    // Prompt values supported
    $output->promptValuesSupported = ['none', 'login', 'consent', 'select_account'];

    // Subject types supported
    $output->subjectTypesSupported = ['public'];

    // ID token signing alg values supported
    $output->idTokenSigningAlgValuesSupported = ['RS256'];

    // Claims supported
    $output->claimsSupported = [
      'sub',
      'name',
      'given_name',
      'family_name',
      'preferred_username',
      'picture',
      'email',
      'email_verified',
      'auth_time',
    ];

    return $output;
  }

  /**
   * Method generateEndpointUrl.
   *
   * Generates an absolute URL for an endpoint.
   *
   * @since 1.0.0
   *
   * @param string $routeName the route name
   * @param string $fallbackPath the fallback path
   * @param string $baseUrl the base URL
   *
   * @return string the absolute URL
   */
  private function generateEndpointUrl(string $routeName, string $fallbackPath, string $baseUrl): string
  {
    try {
      return $this->urlGenerator->generate(
        name: $routeName,
        parameters: [],
        referenceType: UrlGeneratorInterface::ABSOLUTE_URL,
      );
    } catch (RouteNotFoundException) {
      return $this->buildAbsoluteUrl(
        baseUrl: $baseUrl,
        path: $fallbackPath,
      );
    }
  }

  /**
   * Method resolveOptionalEndpoint.
   *
   * Resolves a configured endpoint that can be a URL, a path, or a route name.
   *
   * @since 1.0.0
   *
   * @param string $configuredValue the configured endpoint value
   * @param string $baseUrl the base URL
   *
   * @return string|null the resolved URL or null when not configured
   */
  private function resolveOptionalEndpoint(string $configuredValue, string $baseUrl): ?string
  {
    $value = trim($configuredValue);
    if ('' === $value) {
      return null;
    }

    if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
      return $value;
    }

    if (str_starts_with($value, '/')) {
      return $this->buildAbsoluteUrl(
        baseUrl: $baseUrl,
        path: $value,
      );
    }

    try {
      return $this->urlGenerator->generate(
        name: $value,
        parameters: [],
        referenceType: UrlGeneratorInterface::ABSOLUTE_URL,
      );
    } catch (RouteNotFoundException) {
      return $this->buildAbsoluteUrl(
        baseUrl: $baseUrl,
        path: $value,
      );
    }
  }

  /**
   * Method buildAbsoluteUrl.
   *
   * Builds an absolute URL from a base URL and a path.
   *
   * @since 1.0.0
   *
   * @param string $baseUrl the base URL
   * @param string $path the path
   *
   * @return string the absolute URL
   */
  private function buildAbsoluteUrl(string $baseUrl, string $path): string
  {
    $normalizedBase = rtrim($baseUrl, '/');
    $normalizedPath = '/' . ltrim($path, '/');

    if ('' === $normalizedBase) {
      return $normalizedPath;
    }

    return $normalizedBase . $normalizedPath;
  }
  // #endregion
}
