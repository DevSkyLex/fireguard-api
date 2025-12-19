<?php

declare(strict_types=1);

namespace Auth\Presentation\Resource;

use ApiPlatform\Metadata\{
    ApiResource,
    Post
};
use ApiPlatform\OpenApi\Model\{
    Operation,
    Response
};
use ArrayObject;
use Auth\Presentation\Dto\Input\LoginInput;
use Auth\Presentation\Dto\Output\{
    LoginOutput,
    LogoutOutput
};
use Auth\Presentation\Http\Auth\{
    LoginProcessor,
    LogoutProcessor,
    RefreshTokenProcessor
};
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource AuthResource.
 *
 * @category Resource
 *
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
            name: 'login',
            uriTemplate: '/login',
            input: LoginInput::class,
            output: LoginOutput::class,
            processor: LoginProcessor::class,
            normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
            denormalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_WRITE]],
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
                                'description' => 'Logout and revoke all tokens - requires Bearer access_token header',
                            ],
                            'GetUserInfo' => [
                                'operationId' => 'userinfo',
                                'description' => 'Get authenticated user information - requires Bearer access_token header',
                            ],
                            'IntrospectToken' => [
                                'operationId' => 'introspect_token',
                                'description' => 'Introspect the access token to get its metadata',
                                'parameters' => [
                                    'token' => '$response.body#/access_token',
                                    'token_type_hint' => 'access_token',
                                ],
                            ],
                        ]),
                    ),
                    HttpResponse::HTTP_BAD_REQUEST => new Response(
                        description: 'Invalid request - missing or invalid parameters'
                    ),
                    HttpResponse::HTTP_UNAUTHORIZED => new Response(
                        description: 'Invalid credentials - email or password incorrect'
                    ),
                ],
            ),
        ),
        new Post(
            name: 'refresh',
            uriTemplate: '/refresh',
            input: false,
            output: LoginOutput::class,
            processor: RefreshTokenProcessor::class,
            normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
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
                                'description' => 'Logout and revoke all tokens - requires Bearer access_token header',
                            ],
                            'GetUserInfo' => [
                                'operationId' => 'userinfo',
                                'description' => 'Get authenticated user information - requires Bearer access_token header',
                            ],
                            'IntrospectToken' => [
                                'operationId' => 'introspect_token',
                                'description' => 'Introspect the new access token to get its metadata',
                                'parameters' => [
                                    'token' => '$response.body#/access_token',
                                    'token_type_hint' => 'access_token',
                                ],
                            ],
                        ]),
                    ),
                    HttpResponse::HTTP_UNAUTHORIZED => new Response(
                        description: 'Invalid or expired refresh token'
                    ),
                ],
            ),
        ),
        new Post(
            name: 'logout',
            uriTemplate: '/logout',
            status: 200,
            input: false,
            output: LogoutOutput::class,
            processor: LogoutProcessor::class,
            normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
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
            name: 'mfa_verify',
            uriTemplate: '/mfa/verify',
            input: \Auth\Presentation\Dto\Input\MfaVerifyInput::class,
            output: LoginOutput::class,
            processor: \Auth\Presentation\Http\Auth\MfaVerifyProcessor::class,
            normalizationContext: ['groups' => [OAuthSerializationGroup::TOKEN_READ]],
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
                                'description' => 'Logout and revoke all tokens - requires Bearer access_token header',
                            ],
                            'GetUserInfo' => [
                                'operationId' => 'userinfo',
                                'description' => 'Get authenticated user information - requires Bearer access_token header',
                            ],
                            'IntrospectToken' => [
                                'operationId' => 'introspect_token',
                                'description' => 'Introspect the access token to get its metadata',
                                'parameters' => [
                                    'token' => '$response.body#/access_token',
                                    'token_type_hint' => 'access_token',
                                ],
                            ],
                        ]),
                    ),
                    HttpResponse::HTTP_UNAUTHORIZED => new Response(
                        description: 'Invalid or expired pre-auth token'
                    ),
                    HttpResponse::HTTP_BAD_REQUEST => new Response(
                        description: 'Invalid OTP code'
                    ),
                ],
            ),
        ),
    ]
)]
final class AuthResource
{
}
