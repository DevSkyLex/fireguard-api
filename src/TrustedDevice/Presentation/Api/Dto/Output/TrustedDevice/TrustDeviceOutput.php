<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;
use TrustedDevice\Presentation\Api\Serialization\TrustedDeviceSerializationGroup;

/**
 * DTO TrustDeviceOutput.
 */
final class TrustDeviceOutput
{
  #[Groups([TrustedDeviceSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Trusted device identifier (UUID).',
    readable: true,
    writable: false,
    required: true,
    identifier: true,
    example: '550e8400-e29b-41d4-a716-446655440000',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'readOnly' => true,
    ],
  )]
  public string $deviceId;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Trusted device token used for bypassing MFA on this device.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'td_4f7c2b1e9a0d4c9b',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $token;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Friendly name for the device.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'MacBook Pro',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $deviceName;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  #[ApiProperty(
    description: 'When the trusted device token expires.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '2026-04-29T12:01:00+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public DateTimeImmutable $expiresAt;
}
