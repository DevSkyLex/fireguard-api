<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice\{TrustDeviceOutput, TrustedDeviceOutput};
use TrustedDevice\Presentation\Api\Operation\TrustedDeviceOperations;
use TrustedDevice\Presentation\Api\Processor\TrustedDevice\{RevokeAllDevicesProcessor, RevokeDeviceProcessor, TrustDeviceProcessor};
use TrustedDevice\Presentation\Api\Provider\TrustedDevice\ListTrustedDevicesProvider;
use TrustedDevice\Presentation\Api\Serialization\TrustedDeviceSerializationGroup;

/**
 * Resource TrustedDeviceResource.
 */
#[ApiResource(
  shortName: 'TrustedDevice',
  description: 'Manage trusted devices for 2FA bypass.',
  operations: [
    new Post(
      name: TrustedDeviceOperations::TRUST,
      description: 'Register the current device as trusted.',
      uriTemplate: '/trusted-devices',
      input: false,
      output: TrustDeviceOutput::class,
      processor: TrustDeviceProcessor::class,
      normalizationContext: ['groups' => [TrustedDeviceSerializationGroup::READ]],
      security: "is_granted('trusted_devices.create')",
      openapi: new Operation(
        tags: ['Trusted Devices'],
        summary: 'Trust device',
        description: 'Register the current device as trusted.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'Device trusted successfully',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new GetCollection(
      name: TrustedDeviceOperations::LIST,
      description: 'List all trusted devices for the current user.',
      uriTemplate: '/trusted-devices',
      output: TrustedDeviceOutput::class,
      provider: ListTrustedDevicesProvider::class,
      normalizationContext: ['groups' => [TrustedDeviceSerializationGroup::READ]],
      security: "is_granted('trusted_devices.read')",
      openapi: new Operation(
        tags: ['Trusted Devices'],
        summary: 'List trusted devices',
        description: 'List all trusted devices for the current user.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of trusted devices retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Delete(
      name: TrustedDeviceOperations::REVOKE,
      description: 'Revoke trust for a specific device.',
      uriTemplate: '/trusted-devices/{id}',
      output: false,
      processor: RevokeDeviceProcessor::class,
      security: "is_granted('trusted_devices.revoke')",
      openapi: new Operation(
        tags: ['Trusted Devices'],
        summary: 'Revoke trusted device',
        description: 'Revoke trust for a specific device.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'Device revoked successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Device not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Post(
      name: TrustedDeviceOperations::REVOKE_ALL,
      description: 'Revoke trust for all devices.',
      uriTemplate: '/trusted-devices/revoke-all',
      input: false,
      output: false,
      processor: RevokeAllDevicesProcessor::class,
      security: "is_granted('trusted_devices.revoke')",
      openapi: new Operation(
        tags: ['Trusted Devices'],
        summary: 'Revoke all trusted devices',
        description: 'Revoke trust for all devices.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'All devices revoked successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
  ],
)]
final class TrustedDeviceResource
{
}
