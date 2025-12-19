<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\{
    Operation,
    Response
};
use ArrayObject;
use Otp\Presentation\Api\Dto\ChallengeOutput;
use Otp\Presentation\Api\Dto\CreateChallengeInput;
use Otp\Presentation\Api\Dto\VerifyOtpInput;
use Otp\Presentation\Api\Dto\VerifyOtpOutput;
use Otp\Presentation\Api\Processor\CreateChallengeProcessor;
use Otp\Presentation\Api\Processor\ResendChallengeProcessor;
use Otp\Presentation\Api\Processor\VerifyOtpProcessor;
use Otp\Presentation\Api\Provider\GetChallengeStatusProvider;
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
            name: 'createChallenge',
            uriTemplate: '/challenges',
            input: CreateChallengeInput::class,
            output: ChallengeOutput::class,
            processor: CreateChallengeProcessor::class,
            openapi: new Operation(
                operationId: 'createChallenge',
                tags: ['OTP'],
                summary: 'Create OTP challenge',
                description: 'Creates a new OTP challenge and sends the code via the specified channel.',
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
            name: 'getChallengeStatus',
            uriTemplate: '/challenges/{token}',
            input: false,
            output: ChallengeOutput::class,
            provider: GetChallengeStatusProvider::class,
            openapi: new Operation(
                operationId: 'getChallengeStatus',
                tags: ['OTP'],
                summary: 'Get challenge status',
                description: 'Returns the current status of an OTP challenge.',
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
            name: 'verifyChallenge',
            uriTemplate: '/challenges/{token}/verify',
            input: VerifyOtpInput::class,
            output: VerifyOtpOutput::class,
            processor: VerifyOtpProcessor::class,
            openapi: new Operation(
                operationId: 'verifyChallenge',
                tags: ['OTP'],
                summary: 'Verify challenge',
                description: 'Verifies the OTP code for a challenge.',
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
                ],
            ),
        ),
        new Post(
            name: 'resendChallenge',
            uriTemplate: '/challenges/{token}/resend',
            input: false,
            output: ChallengeOutput::class,
            processor: ResendChallengeProcessor::class,
            openapi: new Operation(
                operationId: 'resendChallenge',
                tags: ['OTP'],
                summary: 'Resend OTP',
                description: 'Resends the OTP code. Subject to cooldown period.',
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
    ]
)]
final class ChallengeResource
{
}
