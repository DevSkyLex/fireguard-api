<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Otp\Presentation\Api\Dto\Output\Config\{ChannelOutput, PurposeOutput};
use Otp\Presentation\Api\Operation\OtpOperations;
use Otp\Presentation\Api\Provider\Config\{ListChannelsProvider, ListPurposesProvider};
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource ConfigResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OtpConfig',
  routePrefix: '/otp',
  description: 'OTP configuration discovery endpoints.',
  operations: [
    new Get(
      name: OtpOperations::LIST_PURPOSES,
      uriTemplate: '/purposes',
      input: false,
      output: PurposeOutput::class,
      provider: ListPurposesProvider::class,
      security: "is_granted('otp_config.read')",
      openapi: new Operation(
        operationId: 'listPurposes',
        tags: ['OTP'],
        summary: 'List OTP purposes',
        description: 'Returns all available OTP purposes with their default TTL and max attempts configuration. Requires otp_config.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of available OTP purposes',
            links: new ArrayObject([
              'CreateChallenge' => [
                'operationId' => 'createChallenge',
                'description' => 'Create a new OTP challenge using one of these purposes',
              ],
            ]),
          ),
        ],
      ),
    ),
    new Get(
      name: OtpOperations::LIST_CHANNELS,
      uriTemplate: '/channels',
      input: false,
      output: ChannelOutput::class,
      provider: ListChannelsProvider::class,
      security: "is_granted('otp_config.read')",
      openapi: new Operation(
        operationId: 'listChannels',
        tags: ['OTP'],
        summary: 'List OTP channels',
        description: 'Returns all available OTP delivery channels. Requires otp_config.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of available OTP channels',
            links: new ArrayObject([
              'CreateChallenge' => [
                'operationId' => 'createChallenge',
                'description' => 'Create a new OTP challenge using one of these channels',
              ],
            ]),
          ),
        ],
      ),
    ),
  ],
)]
final class ConfigResource
{
}
