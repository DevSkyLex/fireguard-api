<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record InterventionRecurrenceRecord.
 *
 * Organization-scoped recurring intervention schedule: a rule (frequency +
 * interval + anchor date, in a fixed timezone) that periodically
 * materializes a template into a real intervention draft.
 *
 * The rule is persisted as discrete columns (`frequency`, `interval_count`,
 * `anchor_date`) — the `rrule` column is reserved for a future freeform
 * expression syntax and is never read by this module today.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_recurrences')]
#[ORM\Index(name: 'idx_intervention_recurrence_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_intervention_recurrence_due', columns: ['is_active', 'next_occurrence_at'])]
class InterventionRecurrenceRecord
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
   * Property organization.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property template.
   *
   * No `onDelete` cascade/set-null: a template backing an active recurrence
   * cannot be deleted while the recurrence still references it (repo
   * precedent for a required, non-cascading reference — see
   * `UserRecord::$otpSecret`'s `user_id` join).
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionTemplateRecord::class)]
  #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false)]
  public ?InterventionTemplateRecord $template = null;

  /**
   * Property name.
   *
   * The intervention name given to each materialized occurrence.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 160)]
  public string $name;

  /**
   * Property siteId.
   *
   * Site (facility) override; null uses the template default.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'site_id', type: 'string', length: 36, nullable: true)]
  public ?string $siteId = null;

  /**
   * Property responsibleId.
   *
   * Responsible member override; null uses the template default.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'responsible_id', type: 'string', length: 36, nullable: true)]
  public ?string $responsibleId = null;

  /**
   * Property frequency.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $frequency = 'monthly';

  /**
   * Property interval.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'interval_count', type: 'integer')]
  public int $interval = 1;

  /**
   * Property anchorDate.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'anchor_date', type: 'datetime_immutable')]
  public DateTimeImmutable $anchorDate;

  /**
   * Property rrule.
   *
   * Reserved for a future freeform recurrence expression. Never read.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  public ?string $rrule = null;

  /**
   * Property timezone.
   *
   * IANA timezone identifier the rule is evaluated in.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 64)]
  public string $timezone = 'UTC';

  /**
   * Property leadTimeDays.
   *
   * Number of days before an occurrence it becomes due for materialization.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'lead_time_days', type: 'integer')]
  public int $leadTimeDays = 7;

  /**
   * Property nextOccurrenceAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'next_occurrence_at', type: 'datetime_immutable')]
  public DateTimeImmutable $nextOccurrenceAt;

  /**
   * Property lastMaterializedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_materialized_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastMaterializedAt = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_active', type: 'boolean')]
  public bool $isActive = true;

  /**
   * Property endAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'end_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $endAt = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}
