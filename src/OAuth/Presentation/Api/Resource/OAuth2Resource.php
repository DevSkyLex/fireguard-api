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
use OAuth\Presentation\Api\Dto\Input\Consent\GrantConsentInput;
use OAuth\Presentation\Api\Dto\Input\Token\{TokenInput, TokenIntrospectionInput, TokenRevocationInput};
use OAuth\Presentation\Api\Dto\Output\Consent\CheckConsentOutput;
use OAuth\Presentation\Api\Dto\Output\Discovery\UserInfoOutput;
use OAuth\Presentation\Api\Dto\Output\Token\{TokenIntrospectionOutput, TokenOutput};
use OAuth\Presentation\Api\Operation\OAuthOperations;
use OAuth\Presentation\Api\Processor\Authorization\AuthorizeProcessor;
use OAuth\Presentation\Api\Processor\Consent\GrantConsentProcessor;
use OAuth\Presentation\Api\Processor\Session\EndSessionProcessor;
use OAuth\Presentation\Api\Processor\Token\{
  IntrospectTokenProcessor,
  IssueTokenProcessor,
  RevokeTokenProcessor
};
use OAuth\Presentation\Api\Provider\Consent\CheckConsentProvider;
use OAuth\Presentation\Api\Provider\Discovery\UserInfoProvider;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OAuth2Resource.
 *
 * @category Resource
 *
 * @version 4.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OAuth2',
  routePrefix: '/oauth2',
  description: 'OAuth2 and OpenID Connect operations',
  operations: [
    new Get(
      name: OAuthOperations::AUTHORIZE,
      uriTemplate: '/authorize',
      input: false,
      output: false,
      provider: AuthorizeProcessor::class,
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Authorize Client',
        description: 'Initiate the OAuth2 authorization code flow (PKCE required).',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'response_type',
            in: 'query',
            required: true,
            description: 'OAuth2 response type (must be code)',
            schema: ['type' => 'string', 'enum' => ['code']],
          ),
          new Parameter(
            name: 'client_id',
            in: 'query',
            required: true,
            description: 'OAuth2 client identifier (UUID)',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
          new Parameter(
            name: 'redirect_uri',
            in: 'query',
            required: true,
            description: 'Redirect URI registered for the client',
            schema: ['type' => 'string', 'format' => 'uri'],
          ),
          new Parameter(
            name: 'scope',
            in: 'query',
            required: false,
            description: 'Space-separated list of requested scopes',
            schema: ['type' => 'string', 'example' => 'openid profile email'],
          ),
          new Parameter(
            name: 'state',
            in: 'query',
            required: false,
            description: 'Opaque state value returned to the client',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'code_challenge',
            in: 'query',
            required: true,
            description: 'PKCE code challenge',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'code_challenge_method',
            in: 'query',
            required: false,
            description: 'PKCE code challenge method (S256 or plain, defaults to plain)',
            schema: ['type' => 'string', 'enum' => ['S256', 'plain']],
          ),
          new Parameter(
            name: 'nonce',
            in: 'query',
            required: false,
            description: 'OIDC nonce value (optional)',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'prompt',
            in: 'query',
            required: false,
            description: 'OIDC prompt values (space-separated)',
            schema: ['type' => 'string', 'example' => 'login consent'],
          ),
          new Parameter(
            name: 'max_age',
            in: 'query',
            required: false,
            description: 'Maximum authentication age in seconds',
            schema: ['type' => 'integer', 'example' => 3600],
          ),
        ],
        responses: [
          HttpResponse::HTTP_FOUND => new Response(
            description: 'Authorization code issued (redirect to redirect_uri).',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'User consent required.',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required.',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid authorization request.',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many authorization requests',
          ),
        ],
      ),
    ),
    new Post(
      name: OAuthOperations::TOKEN,
      uriTemplate: '/token',
      input: TokenInput::class,
      output: TokenOutput::class,
      inputFormats: [
        'jsonld' => ['application/ld+json'],
        'json' => ['application/json'],
        'form' => ['application/x-www-form-urlencoded'],
      ],
      processor: IssueTokenProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_WRITE]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Issue Access Token',
        description: 'Issue an access token using configured OAuth2 grant types (see discovery metadata).',
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
            description: 'Invalid request - missing or invalid parameters',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid client credentials or unauthorized grant type',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many token requests',
          ),
        ],
      ),
    ),
    new Post(
      name: OAuthOperations::REVOKE_TOKEN,
      uriTemplate: '/token/revoke',
      input: TokenRevocationInput::class,
      output: false,
      inputFormats: [
        'jsonld' => ['application/ld+json'],
        'json' => ['application/json'],
        'form' => ['application/x-www-form-urlencoded'],
      ],
      processor: RevokeTokenProcessor::class,
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Revoke Token',
        description: 'Revoke an access token or refresh token as defined in RFC 7009.',
        security: [['bearerAuth' => []]],
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
            description: 'Invalid request - missing token parameter',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid client credentials',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many revocation requests',
          ),
        ],
      ),
    ),
    new Post(
      name: OAuthOperations::INTROSPECT_TOKEN,
      uriTemplate: '/token/introspect',
      input: TokenIntrospectionInput::class,
      output: TokenIntrospectionOutput::class,
      inputFormats: [
        'jsonld' => ['application/ld+json'],
        'json' => ['application/json'],
        'form' => ['application/x-www-form-urlencoded'],
      ],
      processor: IntrospectTokenProcessor::class,
      normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_WRITE]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Introspect Token',
        description: 'Query the authorization server about the current state and metadata of a token as defined in RFC 7662.',
        security: [['bearerAuth' => []]],
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
            description: 'Invalid client credentials',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many introspection requests',
          ),
        ],
      ),
    ),
    new Get(
      name: OAuthOperations::USERINFO,
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
            description: 'Missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Access token lacks required scopes',
          ),
        ],
      ),
    ),
    new Get(
      name: OAuthOperations::CHECK_CONSENT,
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
            description: 'Missing required client_id parameter',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Missing or invalid access token',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many consent checks',
          ),
        ],
      ),
    ),
    new Post(
      name: OAuthOperations::GRANT_CONSENT,
      uriTemplate: '/consent/grant',
      input: GrantConsentInput::class,
      output: false,
      inputFormats: [
        'jsonld' => ['application/ld+json'],
        'json' => ['application/json'],
        'form' => ['application/x-www-form-urlencoded'],
      ],
      processor: GrantConsentProcessor::class,
      denormalizationContext: ['groups' => [OAuthSerializationGroup::CONSENT_WRITE]],
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'Grant User Consent',
        description: 'Approve or deny a consent request and complete the authorization flow.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_FOUND => new Response(
            description: 'Authorization completed (redirect to redirect_uri).',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid consent request.',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required.',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many consent submissions',
          ),
        ],
      ),
    ),
    new Get(
      name: OAuthOperations::END_SESSION,
      uriTemplate: '/logout',
      input: false,
      output: false,
      outputFormats: [
        'json' => ['application/json'],
      ],
      provider: EndSessionProcessor::class,
      openapi: new Operation(
        tags: ['OAuth2'],
        summary: 'End Session',
        description: 'Terminate the current OpenID Connect session and optionally redirect to a client post-logout URI.',
        parameters: [
          new Parameter(
            name: 'id_token_hint',
            in: 'query',
            required: false,
            description: 'ID token previously issued by this server (optional)',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'post_logout_redirect_uri',
            in: 'query',
            required: false,
            description: 'Client post-logout redirect URI (must be registered)',
            schema: ['type' => 'string', 'format' => 'uri'],
          ),
          new Parameter(
            name: 'client_id',
            in: 'query',
            required: false,
            description: 'OAuth2 client identifier (required when post_logout_redirect_uri is provided)',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
          new Parameter(
            name: 'state',
            in: 'query',
            required: false,
            description: 'Opaque state value returned to the client after logout',
            schema: ['type' => 'string'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_FOUND => new Response(
            description: 'Redirect to the post_logout_redirect_uri.',
          ),
          HttpResponse::HTTP_OK => new Response(
            description: 'Logout completed without redirect.',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid logout request.',
          ),
        ],
      ),
    ),
  ],
)]
final class OAuth2Resource
{
}
