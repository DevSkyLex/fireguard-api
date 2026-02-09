<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO HealthOutput.
 *
 * Health check response payload.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class HealthOutput
{
  // #region Properties
  /**
   * Property status.
   *
   * Overall service health status.
   */
  #[Groups(['health:read'])]
  #[ApiProperty(
    description: 'Overall health status of the service',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'healthy',
    openapiContext: [
      'type' => 'string',
      'enum' => ['healthy', 'degraded', 'unhealthy'],
      'readOnly' => true,
    ],
  )]
  public string $status = '';

  /**
   * Property timestamp.
   *
   * Current server time (ISO 8601).
   */
  #[Groups(['health:read'])]
  #[ApiProperty(
    description: 'Current server timestamp (ISO 8601)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '2026-01-30T10:15:30+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public string $timestamp = '';

  /**
   * Property database.
   *
   * Database connectivity status
   * when details are included.
   */
  #[Groups(['health:read:details'])]
  #[ApiProperty(
    description: 'Database connectivity status (when details are enabled)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?bool $database = null;

  /**
   * Property cache.
   *
   * Cache connectivity status
   * when details are included.
   */
  #[Groups(['health:read:details'])]
  #[ApiProperty(
    description: 'Cache connectivity status (when details are enabled)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?bool $cache = null;

  /**
   * Property version.
   *
   * Running application version.
   */
  #[Groups(['health:read'])]
  #[ApiProperty(
    description: 'Running application version',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '1.0.0',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $version = '1.0.0';
  // #endregion
}
