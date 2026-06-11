<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use ArrayObject;
use Auth\Presentation\Api\Dto\Input\PasswordChange\{
  ConfirmPasswordChangeInput,
  RequestPasswordChangeInput
};
use Auth\Presentation\Api\Dto\Output\PasswordChange\{
  ConfirmPasswordChangeOutput,
  RequestPasswordChangeOutput
};
use Auth\Presentation\Api\Operation\PasswordChangeOperations;
use Auth\Presentation\Api\Processor\PasswordChange\{
  ConfirmPasswordChangeProcessor,
  RequestPasswordChangeProcessor
};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource PasswordChangeResource.
 *
 * Two-step password change for the authenticated user:
 * the request step verifies the current password and emails an OTP
 * code; the confirm step verifies the code and applies the change.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'PasswordChange',
  routePrefix: '/me/password',
  description: 'Authenticated password change operations (OTP protected)',
  operations: [
    new Post(
      name: PasswordChangeOperations::REQUEST,
      uriTemplate: '/request',
      input: RequestPasswordChangeInput::class,
      output: RequestPasswordChangeOutput::class,
      processor: RequestPasswordChangeProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::PASSWORD_CHANGE_WRITE]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Request password change',
        description: 'Verifies the current password and sends an OTP code to the user\'s email. The returned challenge token is required for the confirm step.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Verification code sent — use the challenge token in the confirm step',
            links: new ArrayObject([
              'ConfirmChange' => [
                'operationId' => 'confirm_password_change',
                'description' => 'Confirm the password change using the token and code',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing current password',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(
            description: 'Current password is incorrect',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many password change requests',
          ),
        ],
      ),
    ),
    new Post(
      name: PasswordChangeOperations::CONFIRM,
      uriTemplate: '/confirm',
      input: ConfirmPasswordChangeInput::class,
      output: ConfirmPasswordChangeOutput::class,
      processor: ConfirmPasswordChangeProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::PASSWORD_CHANGE_WRITE]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Confirm password change',
        description: 'Confirms the password change using the challenge token, the OTP code received by email, and the new password. All sessions and OAuth tokens are revoked afterwards.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Password changed successfully — all sessions have been revoked',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or invalid parameters',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required, or invalid/expired token/code',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many confirmation attempts or maximum verification attempts exceeded',
          ),
        ],
      ),
    ),
  ],
)]
final class PasswordChangeResource
{
}
