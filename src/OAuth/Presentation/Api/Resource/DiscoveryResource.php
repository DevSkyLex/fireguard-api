<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Get
};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use OAuth\Presentation\Api\Provider\WellKnown\{
  JwksProvider,
  OpenIdConfigurationProvider
};
use OAuth\Presentation\Api\Dto\Output\{
  JwksOutput,
  OpenIdConfigurationOutput
};
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource DiscoveryResource
 * @final
 *
 * OpenID Connect Discovery and JWKS endpoints.
 *
 * This resource exposes standard well-known endpoints for OpenID Connect
 * discovery (RFC 8414) and JSON Web Key Sets (RFC 7517) for signature
 * verification.
 *
 * @category Resource
 * @package OAuth\Presentation\Api\Resource
 * @version 3.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Discovery',
  routePrefix: '/.well-known',
  description: 'OpenID Connect Discovery endpoints',
  operations: [
    new Get(
      name: 'openid_configuration',
      uriTemplate: '/openid-configuration',
      input: false,
      output: OpenIdConfigurationOutput::class,
      provider: OpenIdConfigurationProvider::class,
      outputFormats: ['json' => ['application/json']],
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['OpenID Connect'],
        summary: 'OpenID Connect Discovery',
        description: 'Returns the OpenID Provider Configuration Document as defined in RFC 8414. This endpoint provides all the information needed to configure an OAuth2/OIDC client.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'OpenID Connect configuration returned successfully',
            links: new ArrayObject([
              'GetJWKS' => [
                'operationId' => 'jwks',
                'description' => 'Get the JSON Web Key Set for signature verification',
                'parameters' => [
                  'url' => '$response.body#/jwks_uri',
                ],
              ],
              'IssueToken' => [
                'operationId' => 'token',
                'description' => 'Issue an access token using the token endpoint',
                'parameters' => [
                  'url' => '$response.body#/token_endpoint',
                ],
              ],
              'IntrospectToken' => [
                'operationId' => 'introspect_token',
                'description' => 'Introspect a token using the introspection endpoint',
                'parameters' => [
                  'url' => '$response.body#/introspection_endpoint',
                ],
              ],
              'RevokeToken' => [
                'operationId' => 'revoke_token',
                'description' => 'Revoke a token using the revocation endpoint',
                'parameters' => [
                  'url' => '$response.body#/revocation_endpoint',
                ],
              ],
              'GetUserInfo' => [
                'operationId' => 'userinfo',
                'description' => 'Get user information using the userinfo endpoint',
                'parameters' => [
                  'url' => '$response.body#/userinfo_endpoint',
                ],
              ],
            ]),
          ),
        ],
      ),
    ),
    new Get(
      name: 'jwks',
      uriTemplate: '/jwks.json',
      input: false,
      output: JwksOutput::class,
      provider: JwksProvider::class,
      outputFormats: ['json' => ['application/json']],
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['OpenID Connect'],
        summary: 'JSON Web Key Set',
        description: 'Returns the public keys used to verify JWT signatures as defined in RFC 7517. Use these keys to validate access tokens and ID tokens issued by this server.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'JWKS returned successfully',
            links: new ArrayObject([
              'GetOpenIDConfig' => [
                'operationId' => 'openid_configuration',
                'description' => 'Get the full OpenID Connect configuration',
              ],
              'IntrospectToken' => [
                'operationId' => 'introspect_token',
                'description' => 'Introspect a token to validate it server-side',
              ],
            ]),
          ),
        ],
      ),
    ),
  ]
)]
final class DiscoveryResource
{
}
