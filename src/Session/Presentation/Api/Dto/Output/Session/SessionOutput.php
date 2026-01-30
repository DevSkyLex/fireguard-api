<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Dto\Output\Session;

use ApiPlatform\Metadata\ApiProperty;
use Session\Presentation\Api\Serialization\SessionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO SessionOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The session ID.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Session identifier (UUID).',
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

  /**
   * Property userId.
   *
   * The user ID.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'User identifier (UUID) owning the session.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '0c1b3f6a-8c2e-4f5d-9c0a-2c2b7b9f1c7a',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'readOnly' => true,
    ],
  )]
  public string $userId = '';

  /**
   * Property ipAddress.
   *
   * The client IP address.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Client IP address for the session.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '203.0.113.5',
    openapiContext: [
      'type' => 'string',
      'format' => 'ipv4',
      'readOnly' => true,
    ],
  )]
  public string $ipAddress = '';

  /**
   * Property userAgent.
   *
   * The client user agent.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Client user agent string.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $userAgent = '';

  /**
   * Property deviceType.
   *
   * The device type.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Detected device type.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'desktop',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $deviceType = null;

  /**
   * Property browser.
   *
   * The browser name.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Detected browser name.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'Chrome',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $browser = null;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'ISO 8601 datetime when the session was created.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '2026-01-29T10:15:30+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public string $createdAt = '';

  /**
   * Property lastActivityAt.
   *
   * The last activity timestamp.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'ISO 8601 datetime of the last activity.',
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
  public string $lastActivityAt = '';

  /**
   * Property isActive.
   *
   * Whether the session is active.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Whether the session is active.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
  )]
  public bool $isActive = true;

  /**
   * Property isCurrent.
   *
   * Whether this is the current session.
   */
  #[Groups(groups: [SessionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Whether this session is the current one.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: false,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
  )]
  public bool $isCurrent = false;
  // #endregion
}
