<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Otp\Presentation\Api\Dto\Input\Totp\{ConfirmTotpInput, DisableTotpInput};
use Otp\Presentation\Api\Dto\Output\Totp\{ConfirmTotpOutput, DisableTotpOutput, SetupTotpOutput};
use Otp\Presentation\Api\Operation\OtpOperations;
use Otp\Presentation\Api\Processor\Totp\{ConfirmTotpProcessor, DisableTotpProcessor, SetupTotpProcessor};
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource TotpResource.
 *
 * @category Resource
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Totp',
  routePrefix: '/otp/totp',
  description: 'TOTP (Authenticator App) management.',
  operations: [
    new Post(
      name: OtpOperations::SETUP_TOTP,
      uriTemplate: '/setup',
      input: false,
      output: SetupTotpOutput::class,
      processor: SetupTotpProcessor::class,
      security: "is_granted('otp_totp.setup')",
      openapi: new Operation(
        operationId: 'setupTotp',
        tags: ['OTP'],
        summary: 'Setup TOTP',
        description: 'Generates a new TOTP secret and stores it server-side as a PENDING enrollment for the authenticated user. Returns the secret and a QR code URI for authenticator app setup. Calling this again replaces the pending secret. Requires otp_totp.setup permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'TOTP setup data returned',
            links: new ArrayObject([
              'ConfirmTotp' => [
                'operationId' => 'confirmTotp',
                'description' => 'Confirm the pending secret with a code from the authenticator app',
              ],
            ]),
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'User must be authenticated',
          ),
        ],
      ),
    ),
    new Post(
      name: OtpOperations::CONFIRM_TOTP,
      uriTemplate: '/confirm',
      status: HttpResponse::HTTP_OK,
      input: ConfirmTotpInput::class,
      output: ConfirmTotpOutput::class,
      processor: ConfirmTotpProcessor::class,
      security: "is_granted('otp_totp.confirm')",
      openapi: new Operation(
        operationId: 'confirmTotp',
        tags: ['OTP'],
        summary: 'Confirm TOTP',
        description: 'Verifies a code from the authenticator app against the pending secret and, on success, activates TOTP for the user. Requires otp_totp.confirm permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'TOTP activated successfully',
          ),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(
            description: 'Invalid or expired verification code',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'No pending TOTP setup found; call setup first',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many confirmation attempts',
          ),
        ],
      ),
    ),
    new Post(
      name: OtpOperations::DISABLE_TOTP,
      uriTemplate: '/disable',
      status: HttpResponse::HTTP_OK,
      input: DisableTotpInput::class,
      output: DisableTotpOutput::class,
      processor: DisableTotpProcessor::class,
      security: "is_granted('otp_totp.disable')",
      openapi: new Operation(
        operationId: 'disableTotp',
        tags: ['OTP'],
        summary: 'Disable TOTP',
        description: 'Disables TOTP for the authenticated user. Requires a valid current TOTP code as proof of possession. Requires otp_totp.disable permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'TOTP disabled successfully',
          ),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(
            description: 'Invalid verification code',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'TOTP is not currently enabled for the user',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many disable attempts',
          ),
        ],
      ),
    ),
  ],
)]
final class TotpResource
{
}
