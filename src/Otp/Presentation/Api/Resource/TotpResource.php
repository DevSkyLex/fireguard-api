<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Otp\Presentation\Api\Dto\Output\Totp\SetupTotpOutput;
use Otp\Presentation\Api\Operation\OtpOperations;
use Otp\Presentation\Api\Processor\Totp\SetupTotpProcessor;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource TotpResource.
 *
 * @category Resource
 *
 * @version 1.0.0
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
      openapi: new Operation(
        operationId: 'setupTotp',
        tags: ['OTP'],
        summary: 'Setup TOTP',
        description: 'Generates a new TOTP secret and returns a QR code URI for authenticator app setup.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'TOTP setup data returned',
            links: new ArrayObject([
              'VerifyChallenge' => [
                'operationId' => 'verifyChallenge',
                'description' => 'Verify the TOTP code from the authenticator app',
              ],
            ]),
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'User must be authenticated',
          ),
        ],
      ),
    ),
  ],
)]
final class TotpResource
{
}
