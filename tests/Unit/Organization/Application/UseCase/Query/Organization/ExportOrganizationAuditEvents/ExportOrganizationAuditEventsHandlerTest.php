<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ExportOrganizationAuditEvents;

use Audit\Application\Contract\{AuditExportTooLargeException, OrganizationAuditEntry};
use Audit\Application\Port\Inbound\OrganizationAuditFeedPort;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationAuditEntryProjector;
use Organization\Application\UseCase\Query\Organization\ExportOrganizationAuditEvents\{ExportOrganizationAuditEventsHandler, ExportOrganizationAuditEventsQuery};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

/**
 * Test ExportOrganizationAuditEventsHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportOrganizationAuditEventsHandler::class)]
final class ExportOrganizationAuditEventsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655445501';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655445502';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655445503';

  private const string OUTSIDE_ACTOR_ID = '550e8400-e29b-41d4-a716-446655445505';

  #[Test]
  public function testExportScopesToTheOrganizationFromTheQuery(): void
  {
    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::once())
      ->method('exportForOrganization')
      ->with(self::ORGANIZATION_ID, 'organization.member_added', null, null)
      ->willReturn([$this->makeEntry()]);

    $result = $this->makeHandler(auditFeed: $auditFeed)->__invoke(
      new ExportOrganizationAuditEventsQuery(
        organizationId: self::ORGANIZATION_ID,
        userId: self::USER_ID,
        action: 'organization.member_added',
      ),
    );

    self::assertCount(1, iterator_to_array($result->rows));
  }

  #[Test]
  public function testAnActorWithNoMembershipHereIsNotNamed(): void
  {
    // Same privacy rule as the feed: a platform operator acting on the
    // organization is recorded in the ledger but must not be nameable to it.
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')
      ->willReturnCallback(
        fn (OrganizationId $organizationId, string $userId): ?OrganizationMember => self::OUTSIDE_ACTOR_ID === $userId
          ? null
          : $this->makeActiveMember(),
      );

    $auditFeed = $this->createStub(OrganizationAuditFeedPort::class);
    $auditFeed->method('exportForOrganization')->willReturn([
      $this->makeEntry(),
      $this->makeOutsideActorEntry(),
    ]);

    $result = $this->makeHandler(auditFeed: $auditFeed, memberRepository: $memberRepository)->__invoke(
      new ExportOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID),
    );

    $rows = iterator_to_array($result->rows);

    self::assertTrue($rows[0]->actorIsOrganizationMember);
    self::assertFalse($rows[1]->actorIsOrganizationMember);
  }

  #[Test]
  public function testAMissingOrganizationThrowsBeforeAnythingIsRead(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::never())->method('exportForOrganization');

    $this->expectException(OrganizationNotFoundException::class);

    $this->makeHandler(auditFeed: $auditFeed, organizationRepository: $organizationRepository)->__invoke(
      new ExportOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID),
    );
  }

  #[Test]
  public function testANonMemberThrowsBeforeAnythingIsRead(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn(null);

    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::never())->method('exportForOrganization');

    $this->expectException(OrganizationMemberNotFoundException::class);

    $this->makeHandler(auditFeed: $auditFeed, memberRepository: $memberRepository)->__invoke(
      new ExportOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID),
    );
  }

  #[Test]
  public function testTheExportPermissionIsRequired(): void
  {
    // Not organization.audit.read: reading keeps the data in the product,
    // exporting takes a file out.
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.audit.export'])
      ->willThrowException(OrganizationAccessDeniedException::missingPermission('organization.audit.export'));

    $auditFeed = $this->createMock(OrganizationAuditFeedPort::class);
    $auditFeed->expects(self::never())->method('exportForOrganization');

    $this->expectException(OrganizationAccessDeniedException::class);

    $this->makeHandler(auditFeed: $auditFeed, authorization: $authorization)->__invoke(
      new ExportOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID),
    );
  }

  #[Test]
  public function testTheCapSurfacesFromTheInvocationNotFromIterating(): void
  {
    // If this ever starts throwing on iteration instead, the controller will
    // have sent its headers and the client gets a truncated download rather
    // than a 422.
    $auditFeed = $this->createStub(OrganizationAuditFeedPort::class);
    $auditFeed->method('exportForOrganization')
      ->willThrowException(AuditExportTooLargeException::exceedsCap(matched: 60_000, maxRows: 50_000));

    $this->expectException(AuditExportTooLargeException::class);

    $this->makeHandler(auditFeed: $auditFeed)->__invoke(
      new ExportOrganizationAuditEventsQuery(self::ORGANIZATION_ID, self::USER_ID),
    );
  }

  private function makeHandler(
    OrganizationAuditFeedPort $auditFeed,
    ?OrganizationAuthorizationPort $authorization = null,
    ?OrganizationRepositoryPort $organizationRepository = null,
    ?OrganizationMemberRepositoryPort $memberRepository = null,
  ): ExportOrganizationAuditEventsHandler {
    if (null === $organizationRepository) {
      $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
      $organizationRepository->method('findById')->willReturn($this->makeOrganization());
    }

    if (null === $memberRepository) {
      $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
      $memberRepository->method('findByOrganizationAndUser')->willReturn($this->makeActiveMember());
    }

    return new ExportOrganizationAuditEventsHandler(
      authorization: $authorization ?? $this->createStub(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      auditFeed: $auditFeed,
      projector: new OrganizationAuditEntryProjector($memberRepository),
    );
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

  private function makeEntry(): OrganizationAuditEntry
  {
    return new OrganizationAuditEntry(
      id: '550e8400-e29b-41d4-a716-446655445504',
      action: 'organization.member_added',
      actorType: 'user',
      actorId: self::USER_ID,
      subjectType: 'organization_member',
      subjectId: self::MEMBER_ID,
      metadata: ['user_id' => self::USER_ID],
      occurredAt: '2026-03-15T10:00:00+00:00',
      recordedAt: '2026-03-15T10:00:01+00:00',
    );
  }

  private function makeOutsideActorEntry(): OrganizationAuditEntry
  {
    return new OrganizationAuditEntry(
      id: '550e8400-e29b-41d4-a716-446655445506',
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
}
