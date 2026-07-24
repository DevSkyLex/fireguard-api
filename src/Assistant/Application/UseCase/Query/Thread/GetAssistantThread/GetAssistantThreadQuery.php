<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\GetAssistantThread;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetAssistantThreadQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetAssistantThreadQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $threadId the assistant thread identifier
   * @param string $actorUserId the requesting user identifier
   * @param int $messagesPage the requested messages page number (1-based)
   * @param int $messagesItemsPerPage the requested messages page size
   */
  public function __construct(
    public string $organizationId,
    public string $threadId,
    public string $actorUserId,
    public int $messagesPage = 1,
    public int $messagesItemsPerPage = 50,
  ) {
  }
  // #endregion
}
