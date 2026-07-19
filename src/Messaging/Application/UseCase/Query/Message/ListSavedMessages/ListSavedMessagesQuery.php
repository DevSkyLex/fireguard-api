<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListSavedMessages;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListSavedMessagesQuery.
 *
 * Backs the "Saved items" list (`GET /saved-messages?organization=...`) —
 * the acting member's saved messages across the WHOLE organization, most
 * recently saved first.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListSavedMessagesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
}
