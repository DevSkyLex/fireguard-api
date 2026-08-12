<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\Service;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView};
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Application\Service\OrganizationAuditFeedService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

/**
 * Test OrganizationAuditFeedServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAuditFeedService::class)]
final class OrganizationAuditFeedServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655448001';

  /**
   * The scoping invariant: the criteria are built here, so `organizationId`
   * is always set — a consumer holds no signature through which it could be
   * omitted or widened.
   */
  #[Test]
  public function testListForOrganizationAlwaysScopesTheCriteriaToTheOrganization(): void
  {
    $from = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $to = new DateTimeImmutable('2026-03-31T23:59:59+00:00');

    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('search')
      ->with(
        self::callback(static function (AuditEventSearchCriteria $criteria) use ($from, $to): bool {
          self::assertSame(self::ORGANIZATION_ID, $criteria->organizationId);
          self::assertSame('organization.member_added', $criteria->action);
          self::assertSame($from, $criteria->from);
          self::assertSame($to, $criteria->to);
          self::assertNull($criteria->tenantId, 'No other scope may be smuggled in.');
          self::assertNull($criteria->actorId);

          return true;
        }),
        self::callback(static function (Pagination $pagination): bool {
          self::assertSame(20, $pagination->offset);
          self::assertSame(10, $pagination->limit);

          return true;
        }),
      )
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 10, offset: 20));

    $service = new OrganizationAuditFeedService($repository);

    $result = $service->listForOrganization(
      organizationId: self::ORGANIZATION_ID,
      action: 'organization.member_added',
      from: $from,
      to: $to,
      pagination: new Pagination(offset: 20, limit: 10),
    );

    self::assertSame(0, $result->total);
  }

  /**
   * The reduction invariant. The published entry has no field for actor
   * email, IP, user agent, client/tenant id or chain internals, so the only
   * way any of them could escape is through the metadata payload — which is
   * projected. Asserted on a row deliberately saturated with all of them.
   */
  #[Test]
  public function testListForOrganizationPublishesNoPiiAndProjectsTheMetadata(): void
  {
    $repository = $this->createStub(AuditEventRepositoryPort::class);
    $repository->method('search')->willReturn(new PaginatedResult(
      items: [$this->makeSaturatedView()],
      total: 1,
      limit: 30,
      offset: 0,
    ));

    $service = new OrganizationAuditFeedService($repository);

    $result = $service->listForOrganization(organizationId: self::ORGANIZATION_ID);

    self::assertCount(1, $result->items);
    $entry = $result->items[0];

    self::assertSame('550e8400-e29b-41d4-a716-446655448002', $entry->id);
    self::assertSame('organization.member_added', $entry->action);
    self::assertSame('user', $entry->actorType);
    self::assertSame('550e8400-e29b-41d4-a716-446655448003', $entry->actorId);
    self::assertSame('organization_member', $entry->subjectType);

    // Only the two keys `organization.member_added` is allowed to publish.
    self::assertSame([
      'user_id' => '550e8400-e29b-41d4-a716-446655448004',
      'role_ids' => ['role-1'],
    ], $entry->metadata);

    // Nothing else survived — including the keys a name-based denylist would
    // have had to guess at, and `role_name`, which is not on this action's
    // allowlist even though it is on the role actions'.
    foreach (['organization_id', 'role_name', 'request_id', 'ip', 'ip_hash', 'user_agent', 'invited_email', 'reason', 'session_fingerprint'] as $key) {
      self::assertArrayNotHasKey($key, $entry->metadata);
    }
  }

  private function makeSaturatedView(): AuditEventView
  {
    return new AuditEventView(
      id: '550e8400-e29b-41d4-a716-446655448002',
      action: 'organization.member_added',
      actorType: 'user',
      actorId: '550e8400-e29b-41d4-a716-446655448003',
      actorEmail: 'actor@example.com',
      actorEmailHash: 'a1b2c3',
      subjectType: 'organization_member',
      subjectId: '550e8400-e29b-41d4-a716-446655448004',
      clientId: 'client-1',
      tenantId: 'tenant-1',
      ipAddress: '203.0.113.5',
      ipHash: 'e3b0c4',
      userAgent: 'Mozilla/5.0',
      metadata: [
        'organization_id' => self::ORGANIZATION_ID,
        'user_id' => '550e8400-e29b-41d4-a716-446655448004',
        'role_ids' => ['role-1'],
        'role_name' => 'member',
        'request_id' => 'req-1',
        'ip' => '203.0.113.5',
        'ip_hash' => 'e3b0c4',
        'user_agent' => 'Mozilla/5.0',
        'invited_email' => 'invitee@example.com',
        'reason' => 'Onboarded after the Bordeaux incident review.',
        // A key no denylist would have thought to name — the case that makes
        // an allowlist the only sound default.
        'session_fingerprint' => 'fp-1',
      ],
      occurredAt: '2026-03-15T10:00:00+00:00',
      recordedAt: '2026-03-15T10:00:01+00:00',
      chainId: 'global',
      sequence: 7,
      prevHash: 'prev',
      eventHash: 'hash',
      organizationId: self::ORGANIZATION_ID,
    );
  }
}
