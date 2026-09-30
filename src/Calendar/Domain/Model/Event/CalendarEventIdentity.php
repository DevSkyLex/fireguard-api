<?php

declare(strict_types=1);

namespace Calendar\Domain\Model\Event;

use Calendar\Domain\ValueObject\CalendarEventId;

/** Organization-scoped identity and author of an event. */
final readonly class CalendarEventIdentity
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the organization-scoped event identifier and creating member.
   *
   * @access public
   *
   * @param CalendarEventId $id calendar event identifier
   * @param string $organizationId organization that owns the event
   * @param string $createdByMemberId organization member who created the event
   *
   * @return void
   */
  public function __construct(
    public CalendarEventId $id,
    public string $organizationId,
    public string $createdByMemberId,
  ) {
  }
  // #endregion
}
