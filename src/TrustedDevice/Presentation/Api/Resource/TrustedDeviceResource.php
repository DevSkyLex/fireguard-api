<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use TrustedDevice\Presentation\Api\Dto\TrustDeviceOutput;
use TrustedDevice\Presentation\Api\Dto\TrustedDeviceOutput;
use TrustedDevice\Presentation\Api\Processor\RevokeAllDevicesProcessor;
use TrustedDevice\Presentation\Api\Processor\RevokeDeviceProcessor;
use TrustedDevice\Presentation\Api\Processor\TrustDeviceProcessor;
use TrustedDevice\Presentation\Api\Provider\ListTrustedDevicesProvider;

/**
 * Resource TrustedDeviceResource.
 */
#[ApiResource(
  shortName: 'TrustedDevice',
  description: 'Manage trusted devices for 2FA bypass.',
  operations: [
    new Post(
      name: 'trust',
      description: 'Register the current device as trusted.',
      uriTemplate: '/trusted-devices',
      input: false,
      output: TrustDeviceOutput::class,
      processor: TrustDeviceProcessor::class
    ),
    new GetCollection(
      name: 'list',
      description: 'List all trusted devices for the current user.',
      uriTemplate: '/trusted-devices',
      output: TrustedDeviceOutput::class,
      provider: ListTrustedDevicesProvider::class
    ),
    new Delete(
      name: 'revoke',
      description: 'Revoke trust for a specific device.',
      uriTemplate: '/trusted-devices/{id}',
      output: false,
      processor: RevokeDeviceProcessor::class
    ),
    new Post(
      name: 'revoke-all',
      description: 'Revoke trust for all devices.',
      uriTemplate: '/trusted-devices/revoke-all',
      input: false,
      output: false,
      processor: RevokeAllDevicesProcessor::class
    ),
  ]
)]
final class TrustedDeviceResource
{
}
