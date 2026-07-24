<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\{ListOrganizationInvitationsHandler, ListOrganizationInvitationsQuery};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Domain\ValueObject\Email;

/**
 * Test ListOrganizationInvitationsHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListOrganizationInvitationsHandler::class)]
final class ListOrganizationInvitationsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440900';

  private const string INVITER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INVITED_EMAIL = 'member@fireguard.test';

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::never())->method('findByOrganizationId');
    $invitationRepository->expects(self::never())->method('save');

    $handler = new ListOrganizationInvitationsHandler(
      organizationRepository: $organizationRepository,
      invitationRepository: $invitationRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);
    $this->expectExceptionMessage(self::ORGANIZATION_ID);

    $handler->__invoke(new ListOrganizationInvitationsQuery(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testInvokeReturnsEmptyPaginatedResultWhenThereAreNoInvitations(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('findByOrganizationId')->willReturn([]);
    $invitationRepository->expects(self::never())->method('save');

    $handler = new ListOrganizationInvitationsHandler(
      organizationRepository: $organizationRepository,
      invitationRepository: $invitationRepository,
    );

    $result = $handler->__invoke(new ListOrganizationInvitationsQuery(self::ORGANIZATION_ID));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
    self::assertSame(0, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeMapsActiveInvitationsWithoutExpiringThem(): void
  {
    $invitationId = '11111111-1111-4111-8111-111111111111';
    $roleId = '22222222-2222-4222-8222-222222222222';
    $expiresAt = new DateTimeImmutable('+3 days');

    $invitation = $this->makeInvitation(
      id: $invitationId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: $expiresAt,
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('findByOrganizationId')->willReturn([$invitation]);
    $invitationRepository->method('findRoleIdsForInvitation')->willReturn([$roleId]);
    $invitationRepository->expects(self::never())->method('save');

    $handler = new ListOrganizationInvitationsHandler(
      organizationRepository: $organizationRepository,
      invitationRepository: $invitationRepository,
    );

    $result = $handler->__invoke(new ListOrganizationInvitationsQuery(self::ORGANIZATION_ID));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame(1, $result->total);
    self::assertSame(1, $result->limit);
    self::assertSame(0, $result->offset);

    $item = $result->items[0];
    self::assertSame($invitationId, $item->id);
    self::assertSame(self::ORGANIZATION_ID, $item->organizationId);
    self::assertSame(self::INVITED_EMAIL, $item->email);
    self::assertSame('pending', $item->status);
    self::assertSame(self::INVITER_ID, $item->invitedByUserId);
    self::assertNull($item->acceptedByUserId);
    self::assertNull($item->revokedByUserId);
    self::assertSame($expiresAt, $item->expiresAt);
    self::assertNull($item->acceptedAt);
    self::assertNull($item->revokedAt);
    self::assertSame([$roleId], $item->roleIds);
  }

  #[Test]
  public function testInvokeExpiresElapsedPendingInvitationsAndPersistsThem(): void
  {
    $invitationId = '33333333-3333-4333-8333-333333333333';
    $expiresAt = new DateTimeImmutable('-1 day');

    $invitation = $this->makeInvitation(
      id: $invitationId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: $expiresAt,
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('findByOrganizationId')->willReturn([$invitation]);
    $invitationRepository->method('findRoleIdsForInvitation')->willReturn([]);
    $invitationRepository->expects(self::once())
      ->method('save')
      ->with($invitation);

    $handler = new ListOrganizationInvitationsHandler(
      organizationRepository: $organizationRepository,
      invitationRepository: $invitationRepository,
    );

    $result = $handler->__invoke(new ListOrganizationInvitationsQuery(self::ORGANIZATION_ID));

    self::assertCount(1, $result->items);
    self::assertSame('expired', $result->items[0]->status);
    self::assertSame(OrganizationInvitationStatus::EXPIRED, $invitation->status());
  }

  #[Test]
  public function testInvokeDoesNotExpireNonPendingInvitationsPastTheirExpiry(): void
  {
    $invitationId = '44444444-4444-4444-8444-444444444444';
    $acceptedAt = new DateTimeImmutable('-2 days');
    $acceptedByUserId = '550e8400-e29b-41d4-a716-446655440002';

    $invitation = $this->makeInvitation(
      id: $invitationId,
      status: OrganizationInvitationStatus::ACCEPTED,
      expiresAt: new DateTimeImmutable('-1 day'),
      acceptedAt: $acceptedAt,
      acceptedByUserId: $acceptedByUserId,
    );

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('findByOrganizationId')->willReturn([$invitation]);
    $invitationRepository->method('findRoleIdsForInvitation')->willReturn([]);
    $invitationRepository->expects(self::never())->method('save');

    $handler = new ListOrganizationInvitationsHandler(
      organizationRepository: $organizationRepository,
      invitationRepository: $invitationRepository,
    );

    $result = $handler->__invoke(new ListOrganizationInvitationsQuery(self::ORGANIZATION_ID));

    self::assertCount(1, $result->items);

    $item = $result->items[0];
    self::assertSame('accepted', $item->status);
    self::assertSame($acceptedByUserId, $item->acceptedByUserId);
    self::assertSame($acceptedAt, $item->acceptedAt);
    self::assertSame(OrganizationInvitationStatus::ACCEPTED, $invitation->status());
  }

  private function organization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: self::INVITER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('-30 days'),
    );
  }

  private function makeInvitation(
    string $id,
    OrganizationInvitationStatus $status,
    DateTimeImmutable $expiresAt,
    ?DateTimeImmutable $acceptedAt = null,
    ?string $acceptedByUserId = null,
  ): OrganizationInvitation {
    return OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($id),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      email: new Email(self::INVITED_EMAIL),
      tokenHash: 'hashed-token',
      invitedByUserId: self::INVITER_ID,
      status: $status,
      expiresAt: $expiresAt,
      createdAt: new DateTimeImmutable('-5 days'),
      updatedAt: new DateTimeImmutable('-5 days'),
      acceptedAt: $acceptedAt,
      acceptedByUserId: $acceptedByUserId,
    );
  }
}
