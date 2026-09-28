<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Presence\GetPresence;

use Messaging\Application\Contract\Presence\MemberPresenceView;
use Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort;
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingPresenceCacheKeys};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use User\Application\Port\Inbound\PresencePreferenceReaderPort;

use function array_unique;
use function array_values;
use function is_string;

/** Reads scoped active identities and account preferences with one query per database. */
final readonly class GetPresenceHandler implements QueryHandler
{
  public function __construct(
    private MessagingAccessPolicy $accessPolicy,
    private CachePort $cache,
    private MessagingMemberDirectoryPort $members,
    private PresencePreferenceReaderPort $preferences,
  ) {
  }

  public function __invoke(GetPresenceQuery $query): GetPresenceResult
  {
    $this->accessPolicy->assertCanReadPresence($query->userId, $query->organizationId);
    $memberIds = array_values(array_unique($query->memberIds));
    $users = $this->members->activeUserIdsForMembers($query->organizationId, $memberIds);
    $preferences = $this->preferences->readMany(array_values(array_unique($users)));
    $presences = [];
    foreach ($memberIds as $memberId) {
      $userId = $users[$memberId] ?? null;
      $lastSeenAt = null === $userId ? null : $this->cache->get(MessagingPresenceCacheKeys::key($query->organizationId, $memberId));
      $invisible = null !== $userId && ($preferences[$userId]->invisible ?? false);
      $online = !$invisible && is_string($lastSeenAt);
      $dnd = null !== $userId && ($preferences[$userId]->doNotDisturb ?? false);
      $onlineStatus = $dnd ? 'do_not_disturb' : 'active';
      $presences[] = new MemberPresenceView($memberId, $online, $online ? $lastSeenAt : null, $online ? $onlineStatus : 'offline');
    }

    return new GetPresenceResult($presences);
  }
}
