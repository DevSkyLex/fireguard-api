<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Idempotency;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use DateTimeImmutable;
use OAuth\Application\Contract\Event\Client\ClientLifecycleChangedEvent;
use OAuth\Infrastructure\Adapter\Audit\AuditRecordEventAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves all six public client lifecycle facts preserve subject/time and exclude freeform secret-bearing metadata.
 *
 * @category Unit Test
 */
final class ClientLifecycleAuditProjectionTest extends TestCase
{
  #[DataProvider('actions')]
  public function testPublishedClientFactRequestsTheOwnerAuditAction(string $type, string $action): void
  {
    $at = new DateTimeImmutable('2026-10-12T01:00:00Z');
    $port = $this->createMock(AuditRecordingPort::class);
    $port->expects(self::once())->method('requestRecord')->with(self::callback(static fn (AuditRecordRequest $request): bool => $request->action === $action && 'oauth_client' === $request->subjectType && 'client-id' === $request->subjectId && 'client-id' === $request->clientId && $at === $request->occurredAt && ['owner_event_id' => 'owner-event'] === $request->metadata));
    new AuditRecordEventAdapter($port)->onClientLifecycleChanged(new ClientLifecycleChangedEvent($type, 'owner-event', 'client-id', $at, ['name' => 'Private client label', 'unexpected_secret' => 'must never cross the audit boundary']));
  }

  /**
   * @return iterable<string,array{string,string}> explicit public lifecycle mappings
   */
  public static function actions(): iterable
  {
    yield 'registration' => ['oauth.client.registered', 'oauth.client_registered'];
    yield 'configuration' => ['oauth.client.updated', 'oauth.client_updated'];
    yield 'secret rotation' => ['oauth.client.secret_regenerated', 'oauth.client_secret_regenerated'];
    yield 'activation' => ['oauth.client.activated', 'oauth.client_activated'];
    yield 'deactivation' => ['oauth.client.deactivated', 'oauth.client_deactivated'];
    yield 'deletion' => ['oauth.client.deleted', 'oauth.client_deleted'];
  }
}
