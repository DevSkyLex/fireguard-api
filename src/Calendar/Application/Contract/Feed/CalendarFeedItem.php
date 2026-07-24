<?php

declare(strict_types=1);

namespace Calendar\Application\Contract\Feed;

use DateTimeImmutable;

/**
 * Contract CalendarFeedItem.
 *
 * A single entry of the unified calendar feed (`Calendar\Application\Port\Outbound\Feed`
 * seam), shaped so any provider module (Inspection, Intervention, Maintenance
 * — and Calendar itself, for standalone events) can describe "one thing that
 * occurs on a date" without exposing its own Domain types. Plain,
 * framework-free contract type — never a provider module's Domain object.
 *
 * `title`, `description`, and `status` never carry a fabricated, translated
 * label: `title` is either raw user-entered data (an event's title, an
 * intervention's name) or a short source-neutral phrase, and `status` is the
 * source's raw enum value (e.g. `overdue`, `closed`, `draft`) — the frontend
 * owns labels/colors via its own per-domain tag registries, mirroring
 * {@see \Organization\Application\Contract\Intervention\RecentInterventionSummary}.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarFeedItem
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sourceKey the owning source's key (`calendar_event`, `inspection`, `intervention`, or `maintenance`), used both to label the item and as the aggregator's tie-breaker
   * @param string $id the item identifier, unique within its source (not necessarily unique across sources)
   * @param string $title a short, human-readable headline for the item
   * @param ?string $description an optional longer excerpt of the item's content
   * @param DateTimeImmutable $startsAt when the item starts/occurs; drives feed ordering (ascending)
   * @param ?DateTimeImmutable $endsAt when the item ends, when any
   * @param bool $allDay whether the item spans whole day(s) rather than a specific time
   * @param ?string $facilityId the facility this item is associated with, when any
   * @param ?string $status the source's raw status/due-status value, when any
   * @param string $targetType the type of entity the client should navigate to (e.g. `calendar_event`, `inspection`, `intervention`, `maintenance_schedule`)
   * @param string $targetId the identifier of the entity the client should navigate to
   */
  public function __construct(
    public string $sourceKey,
    public string $id,
    public string $title,
    public ?string $description,
    public DateTimeImmutable $startsAt,
    public ?DateTimeImmutable $endsAt,
    public bool $allDay,
    public ?string $facilityId,
    public ?string $status,
    public string $targetType,
    public string $targetId,
  ) {
  }
  // #endregion
}
