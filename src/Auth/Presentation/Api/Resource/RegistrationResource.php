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
use Auth\Presentation\Api\Dto\Input\Registration\{
  ConfirmRegistrationInput,
  RegisterInput,
  ResendRegistrationInput
};
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Dto\Output\Registration\RegisterOutput;
use Auth\Presentation\Api\Operation\RegistrationOperations;
use Auth\Presentation\Api\Processor\Registration\{
  ConfirmRegistrationProcessor,
  RegisterUserProcessor,
  ResendRegistrationProcessor
};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource RegistrationResource.
 *
 * Public self-service registration: create an account, verify the email via OTP,
 * and log in automatically. Mirrors the password-reset resource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Registration',
  routePrefix: '/auth',
  description: 'Public self-service registration operations',
  operations: [
    new Post(
      name: RegistrationOperations::REGISTER,
      uriTemplate: '/register',
      input: RegisterInput::class,
      output: RegisterOutput::class,
      processor: RegisterUserProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::REGISTRATION_WRITE]],
      openapi: new Operation(
        tags: ['Registration'],
        summary: 'Register a new account',
        description: 'Creates a pending-verification account and emails an OTP verification code.',
        responses: [
          // 201, not 200: unlike the action posts corrected alongside it, this
          // one genuinely creates a resource, so API Platform's default is
          // right and the documentation was what lied.
          HttpResponse::HTTP_CREATED => new Response(
            description: 'Account created. A verification code has been sent to the email.',
            links: new ArrayObject([
              'ConfirmRegistration' => [
                'operationId' => 'confirm_registration',
                'description' => 'Verify the email using the token and code',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'An account already exists with this email',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many registration requests',
          ),
        ],
      ),
    ),
    new Post(
      name: RegistrationOperations::RESEND,
      uriTemplate: '/register/resend',
      status: HttpResponse::HTTP_OK,
      input: ResendRegistrationInput::class,
      output: RegisterOutput::class,
      processor: ResendRegistrationProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::REGISTRATION_WRITE]],
      openapi: new Operation(
        tags: ['Registration'],
        summary: 'Resend the verification code',
        description: 'Resends the email-verification OTP code using the existing challenge token.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'A new verification code has been sent.',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or invalid token',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Verification challenge not found or no longer valid',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Resend cooldown not yet elapsed',
          ),
        ],
      ),
    ),
    new Post(
      name: RegistrationOperations::CONFIRM,
      uriTemplate: '/register/verify',
      status: HttpResponse::HTTP_OK,
      input: ConfirmRegistrationInput::class,
      output: LoginOutput::class,
      processor: ConfirmRegistrationProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::REGISTRATION_WRITE]],
      openapi: new Operation(
        tags: ['Registration'],
        summary: 'Verify email and log in',
        description: 'Verifies the OTP code, activates the account, and returns an access token (and refresh token cookie) so the user is logged in automatically.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Email verified. Access token returned and refresh token cookie set.',
            links: new ArrayObject([
              'RefreshToken' => [
                'operationId' => 'refresh',
                'description' => 'Use the refresh token cookie to get a new access token',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or invalid parameters',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Invalid or expired token/code',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Maximum verification attempts exceeded',
          ),
        ],
      ),
    ),
  ],
)]
final class RegistrationResource
{
}
