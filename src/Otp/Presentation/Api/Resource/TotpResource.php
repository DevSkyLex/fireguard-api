<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Otp\Presentation\Api\Dto\Input\Totp\{ConfirmTotpInput, DisableTotpInput, GenerateRecoveryCodesInput};
use Otp\Presentation\Api\Dto\Output\Totp\{ConfirmTotpOutput, DisableTotpOutput, GenerateRecoveryCodesOutput, RecoveryCodesStatusOutput, SetupTotpOutput};
use Otp\Presentation\Api\Operation\OtpOperations;
use Otp\Presentation\Api\Processor\Totp\{ConfirmTotpProcessor, DisableTotpProcessor, GenerateRecoveryCodesProcessor, SetupTotpProcessor};
use Otp\Presentation\Api\Provider\Totp\GetRecoveryCodesStatusProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource TotpResource.
 *
 * @category Resource
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Totp',
  routePrefix: '/otp/totp',
  description: 'TOTP (Authenticator App) management.',
  operations: [
    new Get(
      name: OtpOperations::GET_RECOVERY_CODES_STATUS,
      uriTemplate: '/recovery-codes',
      output: RecoveryCodesStatusOutput::class,
      provider: GetRecoveryCodesStatusProvider::class,
      security: "is_granted('ROLE_USER')",
      normalizationContext: ['groups' => ['recovery_codes:read'], 'skip_null_values' => false],
      openapi: new Operation(
        operationId: 'getRecoveryCodesStatus',
        tags: ['OTP'],
        summary: 'Read recovery code availability',
        description: 'Returns only the authenticated user remaining code count and current lot generation timestamp. Never returns plaintext codes or stored digests.',
        security: [['bearerAuth' => []]],
        responses: [200 => new Response(description: 'Safe recovery metadata'), 401 => new Response(description: 'Authentication required'), 403 => new Response(description: 'Account unavailable')],
      ),
    ),
    new Post(
      name: OtpOperations::GENERATE_RECOVERY_CODES,
      uriTemplate: '/recovery-codes',
      status: HttpResponse::HTTP_OK,
      input: GenerateRecoveryCodesInput::class,
      output: GenerateRecoveryCodesOutput::class,
      processor: GenerateRecoveryCodesProcessor::class,
      security: "is_granted('otp_totp.confirm')",
      normalizationContext: ['groups' => ['recovery_codes:read']],
      denormalizationContext: ['groups' => ['recovery_codes:write']],
      openapi: new Operation(
        operationId: 'generateRecoveryCodes',
        tags: ['OTP'],
        summary: 'Generate recovery codes',
        description: 'Requires a current code from the active authenticator. Atomically replaces the previous recovery lot with ten single-use 128-bit codes; save the returned codes immediately.',
        security: [['bearerAuth' => []]],
        responses: [200 => new Response(description: 'New recovery lot'), 401 => new Response(description: 'Authentication required'), 403 => new Response(description: 'Permission or active account required'), 404 => new Response(description: 'No active authenticator'), 422 => new Response(description: 'Invalid current authenticator proof'), 429 => new Response(description: 'Generation rate limit exceeded')],
      ),
    ),
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
        description: 'Generates a new TOTP secret and stores it server-side as a PENDING enrollment for the authenticated user. Returns the secret and a QR code URI for authenticator app setup. Calling this again replaces the pending secret during initial enrollment. Enrollment requires the account to remain active when its serialized mutation starts. An active factor must first be disabled with its current code; setup refuses replacement. Requires otp_totp.setup permission.',
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
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'An active authenticator must first be disabled with its current code',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Authenticator enrollment is unavailable for this account',
            content: new ArrayObject([
              'application/ld+json' => ['schema' => ['$ref' => '#/components/schemas/Error.jsonld']],
              'application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
              'application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']],
            ]),
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
        description: 'Verifies a code from the authenticator app against the pending secret and, on success, activates TOTP for the user. An existing active factor cannot be replaced, including when a legacy pending secret exists. Requires otp_totp.confirm permission.',
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
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'An active authenticator cannot be replaced by confirming a pending secret',
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
        description: 'Disables TOTP for the authenticated user with a current authenticator code or an unconsumed recovery code. Clears all remaining recovery codes and pending secrets. A recovery code already used to complete login cannot authorize disable. Requires otp_totp.disable permission.',
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
