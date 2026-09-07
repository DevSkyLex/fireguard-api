<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port for localized post-commit organization access notifications.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationJoinNotificationPort
{
  /**
   * @since 1.0.0
   *
   * @param string $recipientUserId recipient
   * @param string $organizationId scope
   * @param string $requestId request
   * @param string $status committed status
   */
  public function send(string $recipientUserId, string $organizationId, string $requestId, string $status): void;
}
