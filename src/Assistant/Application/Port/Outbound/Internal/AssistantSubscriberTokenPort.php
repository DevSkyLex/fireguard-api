<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound\Internal;

/**
 * Interface AssistantSubscriberTokenPort
 *
 * Mints a token for one already-authorized topic without exposing the JWT vendor API.
 *
 * @category Port
 */
interface AssistantSubscriberTokenPort
{
  // #region Methods
  /**
   * Method create
   *
   * @access public
   *
   * @param string $topic authorized private assistant topic
   *
   * @return string subscriber token with no publication permissions
   */
  public function create(string $topic): string;
  // #endregion
}
