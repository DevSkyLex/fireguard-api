<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record CalendarEventRecord.
 *
 * Scaffolded by lot L2.0 ahead of the feature lot that activates the shared
 * organization calendar. `organization_id` and `facility_id` are plain
 * columns (no ORM association, no foreign key), mirroring
 * `Automation\Infrastructure\Persistence\Doctrine\Record\AutomationRunRecord`'s
 * precedent: Calendar never depends on the Organization or Facility ORM
 * mappings.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'calendar_events')]
#[ORM\Index(name: 'idx_calendar_event_org_starts', columns: ['organization_id', 'starts_at'])]
class CalendarEventRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'title', type: 'string', length: 255)]
  public string $title;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'description', type: 'text', nullable: true)]
  public ?string $description = null;

  /**
   * Property startsAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'starts_at', type: 'datetime_immutable')]
  public DateTimeImmutable $startsAt;

  /**
   * Property endsAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'ends_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $endsAt = null;

  /**
   * Property allDay.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'all_day', type: 'boolean')]
  public bool $allDay = false;

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'facility_id', type: 'string', length: 36, nullable: true)]
  public ?string $facilityId = null;

  /**
   * Property createdByMemberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_by_member_id', type: 'string', length: 36)]
  public string $createdByMemberId;

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
  // #endregion
}
