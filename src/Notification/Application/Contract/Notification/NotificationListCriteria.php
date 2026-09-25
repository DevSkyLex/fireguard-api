<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Notification;

use DateTimeImmutable;

/**
 * Filters shared by the notification collection and its matching count.
 */
final readonly class NotificationListCriteria
{
  /**
   * @param list<string> $hiddenReadCategories category prefixes whose older read notifications are hidden
   */
  public function __construct(
    public bool $onlyUnread = false,
    public ?string $type = null,
    public ?string $category = null,
    public ?string $organizationId = null,
    public ?DateTimeImmutable $hideReadBefore = null,
    public array $hiddenReadCategories = [],
  ) {
  }
}
