<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Presentation\Api\Dto\OpenIdConfigurationOutput;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provider OpenIdConfigurationProvider
 * @final
 *
 * Provider for OpenID Connect Discovery endpoint.
 * Returns metadata about the OpenID Provider's configuration.
 *
 * @category Provider
 * @package Auth\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OpenIdConfigurationOutput>
 */
final readonly class OpenIdConfigurationProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the OpenIdConfigurationProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RequestStack $requestStack The request stack.
   * @param string $issuer The issuer URL.
   */
  public function __construct(
    private RequestStack $requestStack,
    private string $issuer = ''
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the OpenID Connect configuration.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return OpenIdConfigurationOutput The configuration.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OpenIdConfigurationOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    $baseUrl = $request ? $request->getSchemeAndHttpHost() : '';
    $issuer = $this->issuer !== '' ? $this->issuer : $baseUrl;

    $output = new OpenIdConfigurationOutput();

    // Required fields
    $output->issuer = $issuer;
    $output->authorizationEndpoint = $baseUrl . '/api/oauth2/authorize';
    $output->tokenEndpoint = $baseUrl . '/api/oauth2/token';
    $output->userinfoEndpoint = $baseUrl . '/api/oauth2/userinfo';
    $output->jwksUri = $baseUrl . '/api/.well-known/jwks.json';

    // Optional but recommended
    $output->revocationEndpoint = $baseUrl . '/api/oauth2/token/revoke';
    $output->introspectionEndpoint = $baseUrl . '/api/oauth2/token/introspect';

    // Supported values
    $output->scopesSupported = [
      'openid',
      'profile',
      'email',
      'read',
      'write',
    ];

    $output->responseTypesSupported = [
      'code',
    ];

    $output->grantTypesSupported = [
      'authorization_code',
      'client_credentials',
      'refresh_token',
    ];

    $output->tokenEndpointAuthMethodsSupported = [
      'client_secret_post',
      'client_secret_basic',
    ];

    // PKCE support (required for OAuth 2.1)
    $output->codeChallengeMethodsSupported = [
      'S256',
      'plain',
    ];

    $output->subjectTypesSupported = [
      'public',
    ];

    $output->idTokenSigningAlgValuesSupported = [
      'RS256',
    ];

    return $output;
  }
  //#endregion
}
