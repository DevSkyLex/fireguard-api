<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Dto\Output\AuditEvent;

use ApiPlatform\Metadata\ApiProperty;
use Audit\Presentation\Api\Serialization\AuditSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AuditEventOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditEventOutput
{
  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Audit event identifier (UUID).',
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
  public string $id = '';

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Audit action key.',
    readable: true,
    writable: false,
    required: true,
    example: 'auth.login_success',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $action = '';

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Actor type (user, client, system, anonymous).',
    readable: true,
    writable: false,
    required: true,
    example: 'user',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $actorType = '';

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Actor identifier (UUID or external ID).',
    readable: true,
    writable: false,
    required: false,
    example: '0c1b3f6a-8c2e-4f5d-9c0a-2c2b7b9f1c7a',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $actorId = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Actor email (optional, depending on PII settings).',
    readable: true,
    writable: false,
    required: false,
    example: 'user@example.com',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $actorEmail = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Actor email hash (SHA-256).',
    readable: true,
    writable: false,
    required: false,
    example: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $actorEmailHash = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Subject type (token, user, client, etc.).',
    readable: true,
    writable: false,
    required: false,
    example: 'token',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $subjectType = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Subject identifier (UUID or token ID).',
    readable: true,
    writable: false,
    required: false,
    example: 'f4d3b0f0-2b4c-4cdd-9d5a-2d2fd9b3c1a1',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $subjectId = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Client identifier (UUID).',
    readable: true,
    writable: false,
    required: false,
    example: '3e9d2c1a-72d4-4b18-9d4d-9a1234567890',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $clientId = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Tenant identifier (UUID).',
    readable: true,
    writable: false,
    required: false,
    example: '00000000-0000-4000-8000-000000000001',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $tenantId = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Client IP address (optional, depending on PII settings).',
    readable: true,
    writable: false,
    required: false,
    example: '203.0.113.5',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $ipAddress = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'IP hash (SHA-256).',
    readable: true,
    writable: false,
    required: false,
    example: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $ipHash = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'User agent string (if available).',
    readable: true,
    writable: false,
    required: false,
    example: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $userAgent = null;

  /**
   * @var array<string, mixed>
   */
  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Event metadata payload (JSON).',
    readable: true,
    writable: false,
    required: false,
    example: [
      'ip' => '203.0.113.5',
      'action' => 'login',
      'success' => true,
    ],
    openapiContext: [
      'type' => 'object',
      'additionalProperties' => true,
      'readOnly' => true,
    ],
  )]
  public array $metadata = [];

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'ISO 8601 datetime when the event occurred.',
    readable: true,
    writable: false,
    required: true,
    example: '2026-01-30T09:45:00+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public string $occurredAt = '';

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'ISO 8601 datetime when the event was recorded.',
    readable: true,
    writable: false,
    required: true,
    example: '2026-01-30T09:45:01+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public string $recordedAt = '';

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Audit chain identifier.',
    readable: true,
    writable: false,
    required: true,
    example: 'tenant:00000000-0000-4000-8000-000000000001',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $chainId = '';

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Monotonic sequence number within the chain.',
    readable: true,
    writable: false,
    required: true,
    example: 123,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $sequence = 0;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Previous event hash in the chain.',
    readable: true,
    writable: false,
    required: false,
    example: 'cf83e1357eefb8bdf1542850d66d8007d620e4050b5715dc83f4a921d36ce9ce',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $prevHash = null;

  #[Groups(groups: [AuditSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Hash of the event payload (SHA-256).',
    readable: true,
    writable: false,
    required: true,
    example: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $eventHash = '';
}
