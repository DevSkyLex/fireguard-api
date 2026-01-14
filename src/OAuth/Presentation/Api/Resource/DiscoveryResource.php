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
use OAuth\Presentation\Api\Dto\Output\Discovery\{JwksOutput, OpenIdConfigurationOutput};
use OAuth\Presentation\Api\Operation\DiscoveryOperations;
use OAuth\Presentation\Api\Provider\Discovery\{
  JwksProvider,
  OpenIdConfigurationProvider
};
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource DiscoveryResource.
 *
 * @category Resource
 *
 * @version 3.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Discovery',
  routePrefix: '/.well-known',
  description: 'OAuth2/OIDC discovery endpoints',
  operations: [
    new Get(
      name: DiscoveryOperations::OPENID_CONFIGURATION,
      uriTemplate: '/openid-configuration',
      input: false,
      output: OpenIdConfigurationOutput::class,
      provider: OpenIdConfigurationProvider::class,
      outputFormats: ['json' => ['application/json']],
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'OAuth2/OIDC Discovery',
        description: 'Returns the OAuth2 Authorization Server Metadata document (RFC 8414) with optional OpenID Connect extensions when available.',
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
              'EndSession' => [
                'operationId' => 'end_session',
                'description' => 'Terminate the OpenID Connect session',
                'parameters' => [
                  'url' => '$response.body#/end_session_endpoint',
                ],
              ],
            ]),
          ),
        ],
      ),
    ),
    new Get(
      name: DiscoveryOperations::JWKS,
      uriTemplate: '/jwks.json',
      input: false,
      output: JwksOutput::class,
      provider: JwksProvider::class,
      outputFormats: ['json' => ['application/json']],
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'JSON Web Key Set',
        description: 'Returns the public keys used to verify JWT signatures as defined in RFC 7517. Use these keys to validate access tokens issued by this server.',
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
  ],
)]
final class DiscoveryResource
{
}
