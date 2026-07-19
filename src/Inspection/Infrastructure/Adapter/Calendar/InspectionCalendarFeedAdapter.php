<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Calendar;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Port\Outbound\Feed\InspectionCalendarFeedPort;
use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function array_map;
use function sprintf;

/**
 * Adapter InspectionCalendarFeedAdapter.
 *
 * Implements the Calendar module's inspection feed port using the
 * Inspection module's own repository port (never a raw `InspectionRecord`
 * query): {@see InspectionRepositoryPort::findByOrganizationId()} already
 * filters to `recordStatus = 'published'` (excludes draft scratchpads and
 * superseded revisions) and supports the `performedAt` range + sort +
 * hard-limit this adapter needs, so no new query is written here.
 *
 * `title` embeds the inspector's free-form display name (raw user-entered
 * data, not a translated enum label); `status` is the inspection's raw
 * status value — the frontend owns labels/colors.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionCalendarFeedAdapter implements InspectionCalendarFeedPort
{
  // #region Constants
  private const string SOURCE_KEY = 'inspection';

  private const string TARGET_TYPE = 'inspection';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionRepositoryPort $inspections the inspection repository port
   */
  public function __construct(
    private InspectionRepositoryPort $inspections,
  ) {
  }
  // #endregion

  // #region Methods
  public function findBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
  {
    $inspections = $this->inspections->findByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      performedAtFrom: $from->format('c'),
      performedAtTo: $to->format('c'),
      sorting: new Sorting('performedAt', SortDirection::ASC),
      limit: $limit,
      offset: 0,
    );

    return array_map($this->toFeedItem(...), $inspections);
  }

  /**
   * Method toFeedItem.
   *
   * @since 1.0.0
   *
   * @param Inspection $inspection the inspection aggregate
   *
   * @return CalendarFeedItem the mapped feed item
   */
  private function toFeedItem(Inspection $inspection): CalendarFeedItem
  {
    $id = (string) $inspection->id();
    $facilityId = $inspection->facilityId();

    return new CalendarFeedItem(
      sourceKey: self::SOURCE_KEY,
      id: $id,
      title: sprintf('Inspection — %s', $inspection->inspector()->name),
      description: $inspection->notes(),
      startsAt: $inspection->performedAt(),
      endsAt: null,
      allDay: false,
      facilityId: null !== $facilityId ? (string) $facilityId : null,
      status: $inspection->status()->value,
      targetType: self::TARGET_TYPE,
      targetId: $id,
    );
  }
  // #endregion
}
