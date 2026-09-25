<?php

declare(strict_types=1);

namespace Calendar\Domain\Model\Event;

use Calendar\Domain\ValueObject\CalendarEventId;

/** Organization-scoped identity and author of an event. */
final readonly class CalendarEventIdentity
{
  public function __construct(
    public CalendarEventId $id,
    public string $organizationId,
    public string $createdByMemberId,
  ) {
  }
}
