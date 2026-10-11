<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use Messaging\Application\Contract\Subscription\MessagingSubscription;

/**
 * Interface MessagingSubscriptionTokenPort
 *
 * Mints a read-only token for a single already authorized conversation, never a wildcard.
 *
 * @category Port
 */
interface MessagingSubscriptionTokenPort
{
  // #region Methods
  /**
   * Method create
   *
   * Grants no publication topics and applies the configured subscriber lifetime.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param string $conversationId authorized conversation
   *
   * @return MessagingSubscription opaque token and exact topic
   */
  public function create(string $organizationId, string $conversationId): MessagingSubscription;
  // #endregion
}
