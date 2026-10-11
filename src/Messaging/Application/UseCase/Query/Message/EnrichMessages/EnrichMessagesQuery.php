<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\EnrichMessages;

use Messaging\Application\Contract\Message\MessageView;
use Shared\Application\Message\QueryMessage;

/**
 * Class EnrichMessagesQuery
 *
 * Enriches an already authorized message page without introducing per-row lookups.
 *
 * @category UseCase
 */
final readonly class EnrichMessagesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the authorized views and the member whose personal saved state is requested.
   *
   * @access public
   *
   * @param list<MessageView> $views already authorized message views
   * @param string $currentMemberId member resolved by the owning use case
   *
   * @return void
   */
  public function __construct(public array $views, public string $currentMemberId)
  {
  }
  // #endregion
}
