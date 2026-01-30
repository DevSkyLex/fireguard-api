<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;
use TrustedDevice\Presentation\Api\Serialization\TrustedDeviceSerializationGroup;

/**
 * DTO TrustedDeviceOutput.
 */
final class TrustedDeviceOutput
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
  public string $id;

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
  public string $name;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Last time the device was used.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '2026-01-29T12:01:00+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public DateTimeImmutable $lastUsedAt;

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  #[ApiProperty(
    description: 'When the trust expires.',
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

  #[Groups([TrustedDeviceSerializationGroup::READ])]
  #[ApiProperty(
    description: 'When the device was added.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '2026-01-20T09:00:00+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public DateTimeImmutable $createdAt;
}
