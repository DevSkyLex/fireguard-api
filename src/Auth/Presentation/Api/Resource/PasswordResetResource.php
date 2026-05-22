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
use Auth\Presentation\Api\Dto\Input\PasswordReset\{
  ConfirmPasswordResetInput,
  RequestPasswordResetInput,
  ResendPasswordResetInput
};
use Auth\Presentation\Api\Dto\Output\PasswordReset\{
  ConfirmPasswordResetOutput,
  RequestPasswordResetOutput
};
use Auth\Presentation\Api\Operation\PasswordResetOperations;
use Auth\Presentation\Api\Processor\PasswordReset\{
  ConfirmPasswordResetProcessor,
  RequestPasswordResetProcessor,
  ResendPasswordResetProcessor
};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource PasswordResetResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'PasswordReset',
  routePrefix: '/auth/password',
  description: 'Password reset operations',
  operations: [
    new Post(
      name: PasswordResetOperations::REQUEST,
      uriTemplate: '/reset/request',
      input: RequestPasswordResetInput::class,
      output: RequestPasswordResetOutput::class,
      processor: RequestPasswordResetProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::PASSWORD_RESET_WRITE]],
      openapi: new Operation(
        tags: ['Password Reset'],
        summary: 'Request Password Reset',
        description: 'Request a password reset by email. An OTP code will be sent to the user\'s email if the account exists.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Password reset request processed successfully. If the email exists, a code has been sent.',
            links: new ArrayObject([
              'ConfirmReset' => [
                'operationId' => 'confirm_password_reset',
                'description' => 'Confirm the password reset using the token and code',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or invalid email',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many password reset requests',
          ),
        ],
      ),
    ),
    new Post(
      name: PasswordResetOperations::RESEND,
      uriTemplate: '/reset/resend',
      input: ResendPasswordResetInput::class,
      output: RequestPasswordResetOutput::class,
      processor: ResendPasswordResetProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::PASSWORD_RESET_WRITE]],
      openapi: new Operation(
        tags: ['Password Reset'],
        summary: 'Resend Password Reset Code',
        description: 'Resend the password reset OTP code using the existing challenge token.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Password reset code resent successfully.',
            links: new ArrayObject([
              'ConfirmReset' => [
                'operationId' => 'confirm_password_reset',
                'description' => 'Confirm the password reset using the new token and code',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or invalid token',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Reset challenge not found or no longer valid',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Resend cooldown not yet elapsed',
          ),
        ],
      ),
    ),
    new Post(
      name: PasswordResetOperations::CONFIRM,
      uriTemplate: '/reset/confirm',
      input: ConfirmPasswordResetInput::class,
      output: ConfirmPasswordResetOutput::class,
      processor: ConfirmPasswordResetProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::PASSWORD_RESET_WRITE]],
      openapi: new Operation(
        tags: ['Password Reset'],
        summary: 'Confirm Password Reset',
        description: 'Confirm password reset using the token, code, and new password. All active sessions will be terminated.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Password reset confirmed successfully. All sessions have been revoked.',
            links: new ArrayObject([
              'Login' => [
                'operationId' => 'login',
                'description' => 'Login with the new password',
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
            description: 'Too many password reset confirmation attempts or maximum verification attempts exceeded',
          ),
        ],
      ),
    ),
  ],
)]
final class PasswordResetResource
{
}
