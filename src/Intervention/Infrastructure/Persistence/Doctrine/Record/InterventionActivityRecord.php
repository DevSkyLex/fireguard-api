<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionActivityRecord.
 *
 * Persists the intervention activity feed: member-authored comments and
 * system-recorded lifecycle events (creation, status transitions). Every row
 * is immutable once written; the feed is append-only.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_activities')]
#[ORM\Index(name: 'idx_intervention_activity_intervention_created', columns: ['intervention_id', 'created_at'])]
class InterventionActivityRecord
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionRecord::class)]
  #[ORM\JoinColumn(name: 'intervention_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InterventionRecord $intervention = null;

  /**
   * Property organizationId.
   *
   * Denormalized owning organization id, kept alongside the intervention
   * relation so activities can be scoped directly without a join.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property actorId.
   *
   * Organization member id of the member that acted, resolved from the
   * authenticated user at write time. Null for activities that could not be
   * attributed to a member (pure-system writes).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'actor_id', type: 'string', length: 36, nullable: true)]
  public ?string $actorId = null;

  /**
   * Property kind.
   *
   * Either `comment` (member-authored) or `system` (lifecycle event).
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $kind = 'system';

  /**
   * Property event.
   *
   * The specific event name: `comment`, `created`, or `status_changed`.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 32)]
  public string $event = 'created';

  /**
   * Property body.
   *
   * The comment text. Null for system events.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $body = null;

  /**
   * Property payload.
   *
   * Structured event data (e.g. `{"from": "...", "to": "..."}` for
   * `status_changed`). Null when the event carries no structured data.
   *
   * @since 1.0.0
   *
   * @var ?array<string, mixed>
   */
  #[ORM\Column(type: 'json', nullable: true)]
  public ?array $payload = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
}
