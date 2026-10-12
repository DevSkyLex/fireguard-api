<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Idempotency;

use Calendar\Application\Contract\Feed\CalendarFeedIcsIdentity;
use Calendar\Application\Port\Outbound\Feed\CalendarFeedIcsAccessPort;
use Calendar\Application\Service\CalendarFeedTokenSecretFactory;
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyAccountPort;
use User\Application\Port\Inbound\AccountStatusPort;

/**
 * Verifies the subscription and its exact current source grants without managed entity caches.
 *
 * @category Adapter
 */
final readonly class CalendarFeedIcsAccessAdapter implements CalendarFeedIcsAccessPort
{
  /**
   * @param Connection $connection owning main database
   * @param AccountStatusPort $accounts active-only subscription account policy
   * @param HttpIdempotencyAccountPort $accountFacts fresh account tenant and durable revision
   * @param FreshOrganizationAuthorizationPort $organizations fresh active membership and effective grants
   * @param CalendarFeedTokenSecretFactory $secretFactory subscription credential hashing
   */
  public function __construct(private Connection $connection, private AccountStatusPort $accounts, private HttpIdempotencyAccountPort $accountFacts, private FreshOrganizationAuthorizationPort $organizations, private CalendarFeedTokenSecretFactory $secretFactory)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function resolve(string $secret): CalendarFeedIcsIdentity
  {
    /** @var array{id:string, organization_id:string, user_id:string}|false $token */
    $token = $this->connection->fetchAssociative('SELECT id, organization_id, user_id FROM member_calendar_feed_tokens WHERE token_hash = :hash AND revoked_at IS NULL', ['hash' => $this->secretFactory->hash($secret)]);
    if (false === $token) {
      throw new CalendarFeedTokenNotFoundException();
    }
    $userId = (string) $token['user_id'];
    $organizationId = (string) $token['organization_id'];
    $account = $this->accountFacts->current($userId);
    if (null === $account || !$account->active || !$this->accounts->isActive($userId)) {
      throw new CalendarFeedTokenNotFoundException();
    }
    $memberId = $this->organizations->activeMemberId($userId, $organizationId);
    if (null === $memberId || !$this->organizations->hasPermission($userId, $organizationId, 'organization.events.read')) {
      throw new CalendarFeedTokenNotFoundException();
    }
    $sources = ['calendar_event'];
    foreach (['inspection' => 'organization.inspection.read', 'intervention' => 'organization.interventions.read', 'maintenance' => 'organization.maintenance.read'] as $source => $permission) {
      if ($this->organizations->hasPermission($userId, $organizationId, $permission)) {
        $sources[] = $source;
      }
    }

    return new CalendarFeedIcsIdentity((string) $token['id'], $organizationId, $userId, $memberId, $account->securityRevision, $account->tenantId, $sources);
  }
}
