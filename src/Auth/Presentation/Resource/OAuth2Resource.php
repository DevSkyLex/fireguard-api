<?php

declare(strict_types=1);

namespace Auth\Presentation\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Auth\Presentation\Http\OAuth2\IntrospectTokenProcessor;
use Auth\Presentation\Http\OAuth2\IssueTokenProcessor;
use Auth\Presentation\Http\OAuth2\RevokeTokenProcessor;
use Auth\Presentation\Http\WellKnown\UserInfoProvider;
use Auth\Presentation\Dto\Request\TokenInput;
use Auth\Presentation\Dto\Request\TokenIntrospectionInput;
use Auth\Presentation\Dto\Request\TokenRevocationInput;
use Auth\Presentation\Dto\Response\TokenIntrospectionOutput;
use Auth\Presentation\Dto\Response\TokenOutput;
use Auth\Presentation\Dto\Response\UserInfoOutput;
use Auth\Presentation\Serialization\AuthSerializationGroup;

/**
 * Resource OAuth2Resource
 * @final
 *
 * @category Resource
 * @package Auth\Presentation\Resource
 * @version 3.0.0
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
