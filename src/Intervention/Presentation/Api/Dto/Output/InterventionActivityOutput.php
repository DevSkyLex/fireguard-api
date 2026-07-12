<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionActivityOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionActivityOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  public string $intervention = '';

  /**
   * Property kind.
   *
   * Either `comment` (member-authored) or `system` (lifecycle event).
   *
   * @since 1.0.0
   */
  public string $kind = 'system';

  /**
   * Property event.
   *
   * The specific event name: `comment`, `created`, or `status_changed`.
   *
   * @since 1.0.0
   */
  public string $event = 'created';

  /**
   * Property actor.
   *
   * The acting organization member IRI, or null for activities that could
   * not be attributed to a member.
   *
   * @since 1.0.0
   */
  public ?string $actor = null;

  /**
   * Property body.
   *
   * The comment text. Null for system events.
   *
   * @since 1.0.0
   */
  public ?string $body = null;

  /**
   * Property payload.
   *
   * Structured event data (e.g. `{"from": "...", "to": "..."}` for
   * `status_changed`).
   *
   * @since 1.0.0
   *
   * @var ?array<string, mixed>
   */
  public ?array $payload = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';
}
