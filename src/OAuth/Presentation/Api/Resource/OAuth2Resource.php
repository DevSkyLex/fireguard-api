<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Get,
  Post
};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Parameter,
  Response
};
use ArrayObject;
use OAuth\Presentation\Api\Processor\Token\{
  IssueTokenProcessor,
  IntrospectTokenProcessor,
  RevokeTokenProcessor
};
use OAuth\Presentation\Api\Provider\WellKnown\UserInfoProvider;
use OAuth\Presentation\Api\Provider\Consent\CheckConsentProvider;
use OAuth\Presentation\Api\Dto\Input\{
  TokenInput,
  TokenIntrospectionInput,
  TokenRevocationInput
};
use OAuth\Presentation\Api\Dto\Output\{
  TokenIntrospectionOutput,
  TokenOutput,
  UserInfoOutput,
  CheckConsentOutput
};
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OAuth2Resource
 * @final
 *
 * OAuth2 and OpenID Connect API Resource.
 * 
 * This resource exposes OAuth2 token management endpoints and OpenID Connect
 * compliant operations for authentication and authorization.
 *
 * @category Resource
 * @package OAuth\Presentation\Api\Resource
 * @version 4.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OAuth2',
  routePrefix: '/oauth2',
  description: 'OAuth2 and OpenID Connect operations',
  operations: [
    new Post(
      name: 'token',
      uriTemplate: '/token',
      input: TokenInput::class,
      output: TokenOutput::class,
      processor: IssueTokenProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_WRITE]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Issue Access Token',
        description: 'Issue an access token using OAuth2 grant types (password, client_credentials, refresh_token, authorization_code) as defined in RFC 6749.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Access token issued successfully',
            links: new ArrayObject([
              'IntrospectToken' => [
                'operationId' => 'introspect_token',
                'description' => 'Introspect the issued token to get its metadata',
                'parameters' => [
                  'token' => '$response.body#/access_token',
                  'token_type_hint' => 'access_token',
                ],
              ],
              'RevokeToken' => [
                'operationId' => 'revoke_token',
                'description' => 'Revoke the issued token when no longer needed',
                'parameters' => [
                  'token' => '$response.body#/access_token',
                  'token_type_hint' => 'access_token',
                ],
              ],
              'RevokeRefreshToken' => [
                'operationId' => 'revoke_token',
                'description' => 'Revoke the refresh token when no longer needed',
                'parameters' => [
                  'token' => '$response.body#/refresh_token',
                  'token_type_hint' => 'refresh_token',
                ],
              ],
              'GetUserInfo' => [
                'operationId' => 'userinfo',
                'description' => 'Get user information using the access token (pass as Bearer header)',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or invalid parameters'
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid client credentials or unauthorized grant type'
          ),
        ],
      ),
    ),
    new Post(
      name: 'revoke_token',
      uriTemplate: '/token/revoke',
      input: TokenRevocationInput::class,
      output: false,
      processor: RevokeTokenProcessor::class,
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Revoke Token',
        description: 'Revoke an access token or refresh token as defined in RFC 7009.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Token revoked successfully',
            links: new ArrayObject([
              'IssueNewToken' => [
                'operationId' => 'token',
                'description' => 'Issue a new token after revoking the old one',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing token parameter'
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid client credentials'
          ),
        ],
      ),
    ),
    new Post(
      name: 'introspect_token',
      uriTemplate: '/token/introspect',
      input: TokenIntrospectionInput::class,
      output: TokenIntrospectionOutput::class,
      processor: IntrospectTokenProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_WRITE]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Introspect Token',
        description: 'Query the authorization server about the current state and metadata of a token as defined in RFC 7662.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Token introspection result',
            links: new ArrayObject([
              'RevokeToken' => [
                'operationId' => 'revoke_token',
                'description' => 'Revoke the token if it should be invalidated',
                'parameters' => [
                  'token' => '$request.body#/token',
                  'token_type_hint' => '$request.body#/token_type_hint',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid client credentials'
          ),
        ],
      ),
    ),
    new Get(
      name: 'userinfo',
      uriTemplate: '/userinfo',
      input: false,
      output: UserInfoOutput::class,
      provider: UserInfoProvider::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Get User Information',
        description: 'Retrieve claims about the authenticated user as defined by OpenID Connect Core 1.0.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'User information retrieved successfully',
            links: new ArrayObject([
              'CheckConsent' => [
                'operationId' => 'check_consent',
                'description' => 'Check user consent status for a client',
              ],
            ]),
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Missing or invalid access token'
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Access token lacks required scopes'
          ),
        ],
      ),
    ),
    new Get(
      name: 'check_consent',
      uriTemplate: '/consent/check',
      input: false,
      output: CheckConsentOutput::class,
      provider: CheckConsentProvider::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::CONSENT_READ]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Check User Consent',
        description: 'Check if the authenticated user has granted consent for a specific client and scopes.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'client_id',
            in: 'query',
            required: true,
            description: 'The OAuth2 client identifier requesting authorization',
            schema: ['type' => 'string', 'format' => 'uuid', 'example' => '01234567-89ab-cdef-0123-456789abcdef'],
          ),
          new Parameter(
            name: 'scope',
            in: 'query',
            required: false,
            description: 'Space-separated list of requested OAuth2 scopes',
            schema: ['type' => 'string', 'example' => 'openid profile email'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Consent check result',
            links: new ArrayObject([
              'GetUserInfo' => [
                'operationId' => 'userinfo',
                'description' => 'Get user information after consent check',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Missing required client_id parameter'
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Missing or invalid access token'
          ),
        ],
      ),
    ),
  ]
)]
final class OAuth2Resource
{
}
