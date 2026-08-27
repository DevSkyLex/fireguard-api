<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationAuditEventOutput.
 *
 * Organization-scoped view of one audit ledger entry. Deliberately
 * narrower than the platform-level AuditEventOutput: no actor email,
 * no IP address, no user agent, no chain internals — organization
 * members are a lesser-trust audience than platform auditors.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAuditEventOutput
{
  #[Groups(groups: [OrganizationSerializationGroup::READ])]
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

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Audit action key.',
    readable: true,
    writable: false,
    required: true,
    example: 'organization.member_added',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $action = '';

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
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

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Actor identifier (user UUID, if any).',
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

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Actor display name, resolved ONLY when the actor holds a membership in this organization (active or deactivated). Null for a system/client actor, for a user with no membership here — a platform operator acting on the organization, whose identity is not this audience\'s to know — and for a member whose user record is unavailable. Render a neutral, localized placeholder when null; the backend deliberately returns no label so the string stays translatable in the frontend.',
    readable: true,
    writable: false,
    required: false,
    example: 'Jane Doe',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $actorDisplayName = null;

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Audited subject type (organization, organization_member, team, etc.).',
    readable: true,
    writable: false,
    required: false,
    example: 'organization_member',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $subjectType = null;

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Audited subject identifier.',
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

  /**
   * @var array<string, mixed>
   */
  #[Groups(groups: [OrganizationSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Event metadata reduced by the Audit module\'s per-action allowlist. Only identifiers, catalog keys, booleans, counts and organization-owned labels are published; free text, exception details, personal data and keys belonging to another permission\'s surface are dropped, and an action with no allowlist entry yields an empty object.',
    readable: true,
    writable: false,
    required: false,
    example: [
      'user_id' => '00000000-0000-4000-8000-000000000001',
      'role_ids' => ['00000000-0000-4000-8000-000000000002'],
    ],
    openapiContext: [
      'type' => 'object',
      'additionalProperties' => true,
      'readOnly' => true,
    ],
  )]
  public array $metadata = [];

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
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

  #[Groups(groups: [OrganizationSerializationGroup::READ])]
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
}
