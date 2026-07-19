<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\ListAssistantThreads;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListAssistantThreadsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListAssistantThreadsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<AssistantThreadView> $items the page of assistant threads
   * @param int $page the current page number
   * @param int $itemsPerPage the page size
   * @param int $total the total matching assistant thread count
   */
  public function __construct(
    public array $items,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
  // #endregion
}
