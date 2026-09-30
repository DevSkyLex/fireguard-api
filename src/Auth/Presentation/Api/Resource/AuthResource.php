<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Post
};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Auth\Presentation\Api\Dto\Input\Auth\{LoginInput, MfaResendInput};
use Auth\Presentation\Api\Dto\Output\Auth\{
  LoginOutput,
  LogoutOutput
};
use Auth\Presentation\Api\Operation\AuthOperations;
use Auth\Presentation\Api\Processor\Auth\{
  LoginProcessor,
  LogoutProcessor,
  MfaResendProcessor,
  RefreshTokenProcessor
};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource AuthResource.
 *
 * @category Resource
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Auth',
  routePrefix: '/auth',
  description: 'User authentication operations',
  operations: [
    new Post(
      name: AuthOperations::LOGIN,
      uriTemplate: '/login',
      status: HttpResponse::HTTP_OK,
      input: LoginInput::class,
      output: LoginOutput::class,
      processor: LoginProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_WRITE]],
      openapi: new Operation(
        tags: ['Authentication'],
        summary: 'User Login',
        description: 'Authenticate a user with email and password. Returns an access token and sets a refresh token cookie.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Login successful - access token returned',
            links: new ArrayObject([
              'RefreshToken' => [
                'operationId' => 'refresh',
                'description' => 'Use the refresh token cookie (automatically set) to get a new access token',
              ],
              'Logout' => [
                'operationId' => 'logout',
                'description' => self::LOGOUT_LINK_DESCRIPTION,
              ],
              'GetUserInfo' => [
                'operationId' => 'userinfo',
                'description' => self::USER_INFO_LINK_DESCRIPTION,
              ],
              'IntrospectToken' => [
                'operationId' => 'introspect_token',
                'description' => 'Introspect the access token to get its metadata',
                'parameters' => [
                  'token' => self::RESPONSE_ACCESS_TOKEN,
                  'token_type_hint' => 'access_token',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or invalid parameters',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid credentials - email or password incorrect',
            content: new ArrayObject(self::ERROR_CONTENT),
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many authentication attempts',
            content: new ArrayObject(self::RATE_LIMIT_CONTENT),
          ),
        ],
      ),
    ),
    new Post(
      name: AuthOperations::REFRESH,
      uriTemplate: '/refresh',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: LoginOutput::class,
      processor: RefreshTokenProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['Authentication'],
        summary: 'Refresh Access Token',
        description: 'Exchange the refresh token cookie for a new access token. No request body required.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Token refreshed successfully',
            links: new ArrayObject([
              'Logout' => [
                'operationId' => 'logout',
                'description' => self::LOGOUT_LINK_DESCRIPTION,
              ],
              'GetUserInfo' => [
                'operationId' => 'userinfo',
                'description' => self::USER_INFO_LINK_DESCRIPTION,
              ],
              'IntrospectToken' => [
                'operationId' => 'introspect_token',
                'description' => 'Introspect the new access token to get its metadata',
                'parameters' => [
                  'token' => self::RESPONSE_ACCESS_TOKEN,
                  'token_type_hint' => 'access_token',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid or expired refresh token',
            content: new ArrayObject(self::ERROR_CONTENT),
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many refresh requests',
            content: new ArrayObject(self::RATE_LIMIT_CONTENT),
          ),
        ],
      ),
    ),
    new Post(
      name: AuthOperations::LOGOUT,
      uriTemplate: '/logout',
      status: 200,
      input: false,
      output: LogoutOutput::class,
      processor: LogoutProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['Authentication'],
        summary: 'User Logout',
        description: 'Invalidate the current session by revoking tokens and clearing the refresh token cookie.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Logout successful - tokens revoked',
            links: new ArrayObject([
              'Login' => [
                'operationId' => 'login',
                'description' => 'Login again with credentials',
              ],
            ]),
          ),
        ],
      ),
    ),
    new Post(
      name: AuthOperations::MFA_VERIFY,
      uriTemplate: '/mfa/verify',
      status: HttpResponse::HTTP_OK,
      input: \Auth\Presentation\Api\Dto\Input\Auth\MfaVerifyInput::class,
      output: LoginOutput::class,
      processor: \Auth\Presentation\Api\Processor\Auth\MfaVerifyProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['Authentication'],
        summary: 'Verify MFA Challenge',
        description: 'Verify the OTP code using the multi-factor authentication token received during login.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Verification successful - access token returned',
            links: new ArrayObject([
              'RefreshToken' => [
                'operationId' => 'refresh',
                'description' => 'Use the refresh token cookie (automatically set) to get a new access token',
              ],
              'Logout' => [
                'operationId' => 'logout',
                'description' => self::LOGOUT_LINK_DESCRIPTION,
              ],
              'GetUserInfo' => [
                'operationId' => 'userinfo',
                'description' => self::USER_INFO_LINK_DESCRIPTION,
              ],
              'IntrospectToken' => [
                'operationId' => 'introspect_token',
                'description' => 'Introspect the access token to get its metadata',
                'parameters' => [
                  'token' => self::RESPONSE_ACCESS_TOKEN,
                  'token_type_hint' => 'access_token',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid or expired pre-auth token',
            content: new ArrayObject(self::ERROR_CONTENT),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid OTP code',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many MFA verification attempts',
            content: new ArrayObject(self::RATE_LIMIT_CONTENT),
          ),
        ],
      ),
    ),
    new Post(
      name: AuthOperations::MFA_RESEND,
      uriTemplate: '/mfa/resend',
      status: HttpResponse::HTTP_OK,
      input: MfaResendInput::class,
      output: LoginOutput::class,
      processor: MfaResendProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      openapi: new Operation(
        tags: ['Authentication'],
        summary: 'Resend MFA Code',
        description: 'Resend the MFA OTP code using the pre-auth token received during login.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'MFA code resent successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid or expired pre-auth token',
            content: new ArrayObject(self::ERROR_CONTENT),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'MFA challenge not found or no longer valid',
            content: new ArrayObject(self::ERROR_CONTENT),
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Resend cooldown not yet elapsed',
            content: new ArrayObject(self::RATE_LIMIT_CONTENT),
          ),
        ],
      ),
    ),
  ],
)]
final class AuthResource
{
  /**
   * OpenAPI bodies emitted by the centralized error normalizers.
   */
  private const array ERROR_CONTENT = [
    'application/ld+json' => ['schema' => ['$ref' => '#/components/schemas/Error.jsonld']],
    'application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
    'application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
  ];

  /**
   * Problem details emitted by RateLimitFailureSubscriber regardless of Accept.
   */
  private const array RATE_LIMIT_CONTENT = [
    'application/problem+json' => [
      'schema' => [
        'allOf' => [
          ['$ref' => '#/components/schemas/Error'],
          [
            'type' => 'object',
            'required' => ['code', 'retryAfterSeconds'],
            'properties' => [
              'code' => [
                'type' => 'string',
                'enum' => ['rate_limit_exceeded'],
              ],
              'retryAfterSeconds' => [
                'type' => ['integer', 'null'],
                'minimum' => 0,
                'description' => 'Seconds until retry is allowed; null when the delay is unknown.',
              ],
            ],
          ],
        ],
      ],
    ],
  ];

  /**
   * Constant LOGOUT_LINK_DESCRIPTION
   */
  private const LOGOUT_LINK_DESCRIPTION = 'Logout and revoke all tokens - requires Bearer access_token header';

  /**
   * Constant USER_INFO_LINK_DESCRIPTION
   */
  private const USER_INFO_LINK_DESCRIPTION = 'Get authenticated user information - requires Bearer access_token header';

  /**
   * Constant RESPONSE_ACCESS_TOKEN
   */
  private const RESPONSE_ACCESS_TOKEN = '$response.body#/access_token';
}
