<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use User\Presentation\Api\Dto\Input\EmailChange\{ConfirmEmailChangeInput, RequestEmailChangeInput};
use User\Presentation\Api\Dto\Output\EmailChange\{ConfirmEmailChangeOutput, RequestEmailChangeOutput};
use User\Presentation\Api\Operation\EmailChangeOperations;
use User\Presentation\Api\Processor\EmailChange\{CancelEmailChangeProcessor, ConfirmEmailChangeProcessor, RequestEmailChangeProcessor};
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * Resource EmailChangeResource.
 *
 * Secure change of the sign-in email address, in two steps: the
 * authenticated request verifies the current password and emails a
 * confirmation link to the NEW address (with an alert to the old one);
 * the public confirm step validates the token, applies the change and
 * revokes every session and OAuth token. A pending request can be
 * cancelled.
 *
 * The confirm operation is public on purpose: the link lands in the
 * new mailbox where no session may exist, and the repo's registration
 * email-verification pattern is likewise unauthenticated — possession
 * of the emailed secret is the credential.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EmailChange',
  routePrefix: '/me/email-change',
  description: 'Sign-in email address change operations (email confirmation protected)',
  operations: [
    new Post(
      name: EmailChangeOperations::REQUEST,
      uriTemplate: '',
      status: HttpResponse::HTTP_ACCEPTED,
      input: RequestEmailChangeInput::class,
      output: RequestEmailChangeOutput::class,
      processor: RequestEmailChangeProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::EMAIL_CHANGE_READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::EMAIL_CHANGE_WRITE]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Request email change',
        description: 'Verifies the current password, then sends a confirmation link (1 h validity) to the new address and an alert to the current address. A new request replaces any pending one.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_ACCEPTED => new Response(
            description: 'Confirmation link sent to the new address',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - missing or malformed fields',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'This email address cannot be used',
          ),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(
            description: 'Current password is incorrect',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many email change requests',
          ),
        ],
      ),
    ),
    new Post(
      name: EmailChangeOperations::CONFIRM,
      uriTemplate: '/confirm',
      status: HttpResponse::HTTP_OK,
      input: ConfirmEmailChangeInput::class,
      output: ConfirmEmailChangeOutput::class,
      processor: ConfirmEmailChangeProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::EMAIL_CHANGE_READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::EMAIL_CHANGE_WRITE]],
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Confirm email change',
        description: 'Public. Validates the confirmation token received at the new address, applies the change, and revokes every session and OAuth token of the user (the sign-in identifier changed). The token is single-use and expires after 1 hour.',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Email changed — all sessions have been revoked, sign in again with the new address',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid, expired or already-used token',
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'This email address cannot be used',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many confirmation attempts',
          ),
        ],
      ),
    ),
    new Delete(
      name: EmailChangeOperations::CANCEL,
      uriTemplate: '',
      status: HttpResponse::HTTP_NO_CONTENT,
      input: false,
      output: false,
      processor: CancelEmailChangeProcessor::class,
      read: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Cancel pending email change',
        description: 'Cancels the pending email change request of the authenticated user. Idempotent: answers 204 whether or not a request was pending.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'Pending request cancelled (or nothing was pending)',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
        ],
      ),
    ),
  ],
)]
final class EmailChangeResource
{
}
