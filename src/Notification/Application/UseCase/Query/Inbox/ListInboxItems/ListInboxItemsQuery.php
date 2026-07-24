<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Inbox\ListInboxItems;

use DateTimeImmutable;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInboxItemsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInboxItemsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier the feed is scoped to
   * @param string|null $organizationId optional organization filter; when omitted, items across every organization (and account-level ones) are returned
   * @param DateTimeImmutable|null $before cursor: only items strictly before this instant are returned; omit for the first page
   * @param int $limit the requested page size
   */
  public function __construct(
    public string $userId,
    public ?string $organizationId = null,
    public ?DateTimeImmutable $before = null,
    public int $limit = 20,
  ) {
  }
  // #endregion
}
