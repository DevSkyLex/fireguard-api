<?php

declare(strict_types=1);

namespace Calendar\Application\Contract\Feed;

/**
 * Fresh non-secret subscription ownership, account revision and exact feed visibility.
 *
 * @category Contract
 */
final readonly class CalendarFeedIcsIdentity
{
  /**
   * @param string $tokenId verified active subscription record
   * @param string $organizationId current organization scope
   * @param string $userId current owning account
   * @param string $memberId current active membership
   * @param int $securityRevision current account credential revision
   * @param ?string $tenantId current native tenant context
   * @param list<string> $sources exactly permitted calendar source keys
   */
  public function __construct(public string $tokenId, public string $organizationId, public string $userId, public string $memberId, public int $securityRevision, public ?string $tenantId, public array $sources)
  {
  }
}
