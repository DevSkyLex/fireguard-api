<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\WellKnown;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use OAuth\Presentation\Api\Dto\Output\OpenIdConfigurationOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider OpenIdConfigurationProvider
 * @final
 *
 * @category Provider
 * @package OAuth\Presentation\Api\Provider\WellKnown
 * @version 1.0.0
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
   * Initializes a new instance of the
   * OpenIdConfigurationProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RequestStack $requestStack The request stack.
   * @param string $issuer The issuer URL.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    #[Autowire('%env(OAUTH_ISSUER)%')]
    private readonly string $issuer = ''
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

    $output->issuer = $issuer;
    $output->authorizationEndpoint = $baseUrl . '/api/oauth2/authorize';
    $output->tokenEndpoint = $baseUrl . '/api/oauth2/token';
    $output->userinfoEndpoint = $baseUrl . '/api/oauth2/userinfo';
    $output->jwksUri = $baseUrl . '/api/.well-known/jwks.json';
    $output->revocationEndpoint = $baseUrl . '/api/oauth2/token/revoke';
    $output->introspectionEndpoint = $baseUrl . '/api/oauth2/token/introspect';
    $output->endSessionEndpoint = $baseUrl . '/api/oauth2/logout';

    $output->scopesSupported = ['openid', 'profile', 'email', 'read', 'write'];
    $output->responseTypesSupported = ['code'];
    $output->grantTypesSupported = ['authorization_code', 'client_credentials', 'refresh_token'];
    $output->tokenEndpointAuthMethodsSupported = ['client_secret_post', 'client_secret_basic'];
    $output->codeChallengeMethodsSupported = ['S256', 'plain'];
    $output->subjectTypesSupported = ['public'];
    $output->idTokenSigningAlgValuesSupported = ['RS256'];

    return $output;
  }
  //#endregion
}
