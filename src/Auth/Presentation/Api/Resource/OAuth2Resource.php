<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Get,
  Post
};
use Auth\Presentation\Api\Processor\{
  IntrospectTokenProcessor,
  IssueTokenProcessor,
  RevokeTokenProcessor
};
use Auth\Presentation\Api\Provider\{
  AuthorizeProvider,
  UserInfoProvider
};
use Auth\Presentation\Api\Dto\{
  AuthorizationOutput,
  TokenInput,
  TokenIntrospectionInput,
  TokenIntrospectionOutput,
  TokenOutput,
  TokenRevocationInput,
  UserInfoOutput
};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;

/**
 * Resource OAuth2Resource
 * @final
 *
 * Unified API Platform resource for OAuth2 and OpenID Connect operations.
 * Implements:
 * - RFC 6749 (OAuth 2.0)
 * - RFC 7009 (Token Revocation)
 * - RFC 7662 (Token Introspection)
 * - OpenID Connect Core 1.0
 *
 * @category Resource
 * @package Auth\Presentation\Api\Resource
 * @version 3.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749
 * @see https://datatracker.ietf.org/doc/html/rfc7009
 * @see https://datatracker.ietf.org/doc/html/rfc7662
 * @see https://openid.net/specs/openid-connect-core-1_0.html
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OAuth2',
  routePrefix: '/oauth2',
  description: 'OAuth2 and OpenID Connect operations',
  operations: [
    new Get(
      name: 'authorize',
      description: 'Authorization request (RFC 6749)',
      uriTemplate: '/authorize',
      input: false,
      output: AuthorizationOutput::class,
      provider: AuthorizeProvider::class,
    ),
    new Post(
      name: 'token',
      description: 'Token issuance (RFC 6749)',
      uriTemplate: '/token',
      input: TokenInput::class,
      output: TokenOutput::class,
      processor: IssueTokenProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::WRITE]]
    ),
    new Post(
      name: 'revoke_token',
      description: 'Token revocation (RFC 7009)',
      uriTemplate: '/token/revoke',
      input: TokenRevocationInput::class,
      output: false,
      processor: RevokeTokenProcessor::class,
    ),
    new Post(
      name: 'introspect_token',
      description: 'Token introspection (RFC 7662)',
      uriTemplate: '/token/introspect',
      input: TokenIntrospectionInput::class,
      output: TokenIntrospectionOutput::class,
      processor: IntrospectTokenProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::WRITE]]
    ),
    new Get(
      name: 'userinfo',
      description: 'UserInfo endpoint (OpenID Connect)',
      uriTemplate: '/userinfo',
      input: false,
      output: UserInfoOutput::class,
      provider: UserInfoProvider::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]]
    )
  ]
)]
final class OAuth2Resource
{
}
