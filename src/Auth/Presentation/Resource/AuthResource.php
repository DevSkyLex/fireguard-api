<?php

declare(strict_types=1);

namespace Auth\Presentation\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Auth\Presentation\Dto\Request\LoginInput;
use Auth\Presentation\Dto\Response\LoginOutput;
use Auth\Presentation\Dto\Response\LogoutOutput;
use Auth\Presentation\Http\Auth\LoginProcessor;
use Auth\Presentation\Http\Auth\LogoutProcessor;
use Auth\Presentation\Http\Auth\RefreshTokenProcessor;
use Auth\Presentation\Serialization\AuthSerializationGroup;

/**
 * Resource AuthResource
 * @final
 *
 * API Platform resource for user authentication.
 *
 * @category Resource
 * @package Auth\Presentation\Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Auth',
  routePrefix: '/auth',
  description: 'User authentication operations',
  operations: [
    new Post(
      name: 'login',
      description: 'Login with email and password',
      uriTemplate: '/login',
      input: LoginInput::class,
      output: LoginOutput::class,
      processor: LoginProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::WRITE]]
    ),
    new Post(
      name: 'refresh',
      description: 'Refresh access token using refresh token cookie',
      uriTemplate: '/refresh',
      input: false,
      output: LoginOutput::class,
      processor: RefreshTokenProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]]
    ),
    new Post(
      name: 'logout',
      description: 'Logout and revoke tokens',
      uriTemplate: '/logout',
      status: 200,
      input: false,
      output: LogoutOutput::class,
      processor: LogoutProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]]
    )
  ]
)]
final class AuthResource
{
}
