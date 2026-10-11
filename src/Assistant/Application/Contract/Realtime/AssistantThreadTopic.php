<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Realtime;

use function sprintf;

/**
 * Class AssistantThreadTopic
 *
 * Keeps the stable private assistant topic shared by authorized subscription and publication.
 *
 * @category Contract
 */
final class AssistantThreadTopic
{
  // #region Methods
  /**
   * Method forThread
   *
   * @access public
   *
   * @param string $organizationId owning organization identity
   * @param string $threadId member-private thread identity
   *
   * @return string private realtime topic
   */
  public static function forThread(string $organizationId, string $threadId): string
  {
    return sprintf('/organizations/%s/assistant/threads/%s', $organizationId, $threadId);
  }
  // #endregion
}
