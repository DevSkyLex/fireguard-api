<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents;

use Audit\Application\Contract\OrganizationAuditEntry;
use Audit\Application\Port\Inbound\OrganizationAuditFeedPort;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationAuditEntryProjector;
use Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents\{ListOrganizationAuditEventsHandler, ListOrganizationAuditEventsQuery, OrganizationAuditEventResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

/**
 * Test ListOrganizationAuditEventsHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListOrganizationAuditEventsHandler::class)]
final class ListOrganizationAuditEventsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655445501';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655445502';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655445503';

  private const string EVENT_ID = '550e8400-e29b-41d4-a716-446655445504';

  private const string OUTSIDE_ACTOR_ID = '550e8400-e29b-41d4-a716-446655445505';

  private const string OUTSIDE_EVENT_ID = '550e8400-e29b-41d4-a716-446655445506';

  #[Test]
  public function testInvokeScopesTheFeedToTheOrganizationAndMapsEveryField(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->makeOrganization());

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn($this->makeActiveMember());

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.audit.read']);

    $from = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $to = new DateTimeImmutable('2026-03-31T23:59:59+00:00');

    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::once())
      ->method('listForOrganization')
      ->with(
        self::ORGANIZATION_ID,
        'organization.member_added',
        $from,
        $to,
        self::callback(static function (Pagination $pagination): bool {
          self::assertSame(30, $pagination->offset);
          self::assertSame(15, $pagination->limit);

          return true;
        }),
      )
      ->willReturn(new PaginatedResult(
        items: [$this->makeEntry()],
        total: 42,
        limit: 15,
        offset: 30,
      ));

    $handler = new ListOrganizationAuditEventsHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      auditFeed: $auditFeed,
      projector: new OrganizationAuditEntryProjector($memberRepository),
    );

    $result = $handler->__invoke(new ListOrganizationAuditEventsQuery(
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      action: 'organization.member_added',
      from: $from,
      to: $to,
      pagination: new Pagination(offset: 30, limit: 15),
    ));

    self::assertSame(42, $result->total);
    self::assertSame(15, $result->limit);
    self::assertSame(30, $result->offset);
    self::assertCount(1, $result->items);

    $event = $result->items[0];
    self::assertInstanceOf(OrganizationAuditEventResult::class, $event);
    self::assertSame(self::EVENT_ID, $event->id);
    self::assertSame('organization.member_added', $event->action);
    self::assertSame('user', $event->actorType);
    self::assertSame(self::USER_ID, $event->actorId);
    self::assertTrue($event->actorIsOrganizationMember);
    self::assertSame('organization_member', $event->subjectType);
    self::assertSame(self::MEMBER_ID, $event->subjectId);
    self::assertSame('2026-03-15T10:00:00+00:00', $event->occurredAt);
    self::assertSame('2026-03-15T10:00:01+00:00', $event->recordedAt);
    self::assertSame(['user_id' => self::USER_ID, 'role_ids' => ['role-1']], $event->metadata);
  }

  /**
   * An actor recorded against this organization is not necessarily one of its
   * people — a platform operator acting on it is in the ledger too. Only an
   * actor holding a membership here is flagged nameable; everyone else is
   * not, and the presentation layer therefore never resolves their name.
   */
  #[Test]
  public function testInvokeDoesNotFlagANonMemberActorAsNameable(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->makeOrganization());

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')
      ->willReturnCallback(fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => self::USER_ID === $userId ? $this->makeActiveMember() : null);

    $auditFeed = $this->createStub(OrganizationAuditFeedPort::class);
    $auditFeed->method('listForOrganization')->willReturn(new PaginatedResult(
      items: [$this->makeEntry(), $this->makeOutsideActorEntry(), $this->makeSystemActorEntry()],
      total: 3,
      limit: 30,
      offset: 0,
    ));

    $handler = new ListOrganizationAuditEventsHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      auditFeed: $auditFeed,
      projector: new OrganizationAuditEntryProjector($memberRepository),
    );

    $result = $handler->__invoke(new ListOrganizationAuditEventsQuery(
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
    ));

    self::assertCount(3, $result->items);
    self::assertTrue($result->items[0]->actorIsOrganizationMember, 'A member actor is nameable.');
    self::assertFalse($result->items[1]->actorIsOrganizationMember, 'A user with no membership here must not be nameable.');
    self::assertSame(self::OUTSIDE_ACTOR_ID, $result->items[1]->actorId, 'The opaque actor id is still published.');
    self::assertFalse($result->items[2]->actorIsOrganizationMember, 'A system actor has no name to resolve.');
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::never())->method('listForOrganization');

    $handler = new ListOrganizationAuditEventsHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      auditFeed: $auditFeed,
      projector: new OrganizationAuditEntryProjector($this->createStub(OrganizationMemberRepositoryPort::class)),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new ListOrganizationAuditEventsQuery(
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenCallerHasNoActiveMembership(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->makeOrganization());

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn(null);

    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::never())->method('listForOrganization');

    $handler = new ListOrganizationAuditEventsHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      auditFeed: $auditFeed,
      projector: new OrganizationAuditEntryProjector($memberRepository),
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new ListOrganizationAuditEventsQuery(
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMemberLacksTheAuditReadPermission(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->makeOrganization());

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn($this->makeActiveMember());

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->willThrowException(OrganizationAccessDeniedException::missingPermission('organization.audit.read'));

    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::never())->method('listForOrganization');

    $handler = new ListOrganizationAuditEventsHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      auditFeed: $auditFeed,
      projector: new OrganizationAuditEntryProjector($memberRepository),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new ListOrganizationAuditEventsQuery(
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
    ));
  }

  private function makeOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Bordeaux'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
    );
  }

  private function makeActiveMember(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-5 days'),
    );
  }

  /**
   * An entry as the Audit module publishes it: already scoped, already
   * projected. The handler must not re-filter it — the reduction is the
   * producer's invariant, asserted in
   * `Tests\Unit\Audit\Application\Service\OrganizationAuditMetadataProjectionTest`.
   */
  private function makeEntry(): OrganizationAuditEntry
  {
    return new OrganizationAuditEntry(
      id: self::EVENT_ID,
      action: 'organization.member_added',
      actorType: 'user',
      actorId: self::USER_ID,
      subjectType: 'organization_member',
      subjectId: self::MEMBER_ID,
      metadata: ['user_id' => self::USER_ID, 'role_ids' => ['role-1']],
      occurredAt: '2026-03-15T10:00:00+00:00',
      recordedAt: '2026-03-15T10:00:01+00:00',
    );
  }

  private function makeOutsideActorEntry(): OrganizationAuditEntry
  {
    return new OrganizationAuditEntry(
      id: self::OUTSIDE_EVENT_ID,
      action: 'organization.suspended',
      actorType: 'user',
      actorId: self::OUTSIDE_ACTOR_ID,
      subjectType: 'organization',
      subjectId: self::ORGANIZATION_ID,
      metadata: [],
      occurredAt: '2026-03-16T10:00:00+00:00',
      recordedAt: '2026-03-16T10:00:01+00:00',
    );
  }

  private function makeSystemActorEntry(): OrganizationAuditEntry
  {
    return new OrganizationAuditEntry(
      id: '550e8400-e29b-41d4-a716-446655445507',
      action: 'approval.expired',
      actorType: 'system',
      actorId: null,
      subjectType: 'approval_request',
      subjectId: '550e8400-e29b-41d4-a716-446655445508',
      metadata: ['action_type' => 'equipment_decommission'],
      occurredAt: '2026-03-17T10:00:00+00:00',
      recordedAt: '2026-03-17T10:00:01+00:00',
    );
  }
}
