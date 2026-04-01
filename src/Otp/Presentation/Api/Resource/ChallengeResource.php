<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Otp\Presentation\Api\Dto\Input\Challenge\{CreateChallengeInput, VerifyOtpInput};
use Otp\Presentation\Api\Dto\Output\Challenge\{ChallengeOutput, VerifyOtpOutput};
use Otp\Presentation\Api\Operation\OtpOperations;
use Otp\Presentation\Api\Processor\Challenge\{CreateChallengeProcessor, ResendChallengeProcessor, VerifyOtpProcessor};
use Otp\Presentation\Api\Provider\Challenge\GetChallengeStatusProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource ChallengeResource.
 *
 * @category Resource
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Challenge',
  routePrefix: '/otp',
  description: 'OTP challenge management for verification and 2FA.',
  operations: [
    new Post(
      name: OtpOperations::CREATE_CHALLENGE,
      uriTemplate: '/challenges',
      input: CreateChallengeInput::class,
      output: ChallengeOutput::class,
      processor: CreateChallengeProcessor::class,
      security: "is_granted('otp_challenges.create')",
      openapi: new Operation(
        operationId: 'createChallenge',
        tags: ['OTP'],
        summary: 'Create OTP challenge',
        description: 'Creates a new OTP challenge and sends the code via the specified channel. Requires otp_challenges.create permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'OTP challenge created successfully',
            links: new ArrayObject([
              'GetChallengeStatus' => [
                'operationId' => 'getChallengeStatus',
                'description' => 'Check the challenge status',
                'parameters' => [
                  'token' => '$response.body#/token',
                ],
              ],
              'VerifyChallenge' => [
                'operationId' => 'verifyChallenge',
                'description' => 'Verify the OTP code',
                'parameters' => [
                  'token' => '$response.body#/token',
                ],
              ],
              'ResendChallenge' => [
                'operationId' => 'resendChallenge',
                'description' => 'Resend the OTP if canResendIn is 0',
                'parameters' => [
                  'token' => '$response.body#/token',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request parameters',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Rate limit exceeded',
          ),
        ],
      ),
    ),
    new Get(
      name: OtpOperations::GET_CHALLENGE_STATUS,
      uriTemplate: '/challenges/{token}',
      input: false,
      output: ChallengeOutput::class,
      provider: GetChallengeStatusProvider::class,
      security: "is_granted('otp_challenges.read')",
      openapi: new Operation(
        operationId: 'getChallengeStatus',
        tags: ['OTP'],
        summary: 'Get challenge status',
        description: 'Returns the current status of an OTP challenge. Requires otp_challenges.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Challenge status retrieved',
            links: new ArrayObject([
              'VerifyChallenge' => [
                'operationId' => 'verifyChallenge',
                'description' => 'Verify the OTP code if status is pending',
              ],
              'ResendChallenge' => [
                'operationId' => 'resendChallenge',
                'description' => 'Resend the OTP if canResendIn is 0',
              ],
            ]),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Challenge not found',
          ),
        ],
      ),
    ),
    new Post(
      name: OtpOperations::VERIFY_CHALLENGE,
      uriTemplate: '/challenges/{token}/verify',
      input: VerifyOtpInput::class,
      output: VerifyOtpOutput::class,
      processor: VerifyOtpProcessor::class,
      security: "is_granted('otp_challenges.verify')",
      openapi: new Operation(
        operationId: 'verifyChallenge',
        tags: ['OTP'],
        summary: 'Verify challenge',
        description: 'Verifies the OTP code for a challenge. Requires otp_challenges.verify permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'OTP verified successfully',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid OTP code',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Challenge not found',
          ),
          HttpResponse::HTTP_GONE => new Response(
            description: 'Challenge expired or max attempts reached',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Too many OTP verification attempts',
          ),
        ],
      ),
    ),
    new Post(
      name: OtpOperations::RESEND_CHALLENGE,
      uriTemplate: '/challenges/{token}/resend',
      input: false,
      output: ChallengeOutput::class,
      processor: ResendChallengeProcessor::class,
      security: "is_granted('otp_challenges.resend')",
      openapi: new Operation(
        operationId: 'resendChallenge',
        tags: ['OTP'],
        summary: 'Resend OTP',
        description: 'Resends the OTP code. Subject to cooldown period. Requires otp_challenges.resend permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'OTP resent successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Challenge not found',
          ),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(
            description: 'Resend cooldown not yet elapsed',
          ),
        ],
      ),
    ),
  ],
)]
final class ChallengeResource
{
}
