<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\ListAssistantThreads;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListAssistantThreadsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListAssistantThreadsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the requesting user identifier
   * @param int $page the requested page number (1-based)
   * @param int $itemsPerPage the requested page size
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
  // #endregion
}
