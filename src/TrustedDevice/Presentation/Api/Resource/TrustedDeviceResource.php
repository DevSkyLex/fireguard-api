<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Post};
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
    ),
    new GetCollection(
      name: TrustedDeviceOperations::LIST,
      description: 'List all trusted devices for the current user.',
      uriTemplate: '/trusted-devices',
      output: TrustedDeviceOutput::class,
      provider: ListTrustedDevicesProvider::class,
      normalizationContext: ['groups' => [TrustedDeviceSerializationGroup::READ]],
    ),
    new Delete(
      name: TrustedDeviceOperations::REVOKE,
      description: 'Revoke trust for a specific device.',
      uriTemplate: '/trusted-devices/{id}',
      output: false,
      processor: RevokeDeviceProcessor::class,
    ),
    new Post(
      name: TrustedDeviceOperations::REVOKE_ALL,
      description: 'Revoke trust for all devices.',
      uriTemplate: '/trusted-devices/revoke-all',
      input: false,
      output: false,
      processor: RevokeAllDevicesProcessor::class,
    ),
  ],
)]
final class TrustedDeviceResource
{
}
