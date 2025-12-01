<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Auth\Presentation\Api\Dto\LoginInput;
use Auth\Presentation\Api\Dto\LoginOutput;
use Auth\Presentation\Api\Processor\LoginProcessor;
use Auth\Presentation\Api\Processor\LogoutProcessor;
use Auth\Presentation\Api\Processor\RefreshTokenProcessor;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;

/**
 * Resource AuthResource
 * @final
 *
 * API Platform resource for user authentication.
 * Provides login, logout, and token refresh endpoints.
 *
 * - POST /auth/login - Login with email/password, returns access_token + refresh_token cookie
 * - POST /auth/refresh - Refresh access_token using refresh_token cookie
 * - POST /auth/logout - Logout and clear refresh_token cookie
 *
 * @category Resource
 * @package Auth\Presentation\Api\Resource
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
      input: false,
      output: false,
      processor: LogoutProcessor::class
    )
  ]
)]
final class AuthResource
{
}
