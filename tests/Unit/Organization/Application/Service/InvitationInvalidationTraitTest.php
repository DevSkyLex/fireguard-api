<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationInvitationRepositoryPort;
use Organization\Application\Service\InvitationInvalidationTrait;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus};
use PHPUnit\Framework\Attributes\{CoversTrait, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\ValueObject\Email;
use Tests\Unit\Organization\Application\Service\Double\InvitationInvalidationHost;

/**
 * Test InvitationInvalidationTrait.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversTrait(InvitationInvalidationTrait::class)]
final class InvitationInvalidationTraitTest extends TestCase
{
  private const string INVITATION_ID = '550e8400-e29b-41d4-a716-446655445500';

  #[Test]
  public function testRevokesAndPersistsAPendingInvitation(): void
  {
    $invitation = $this->invitation(OrganizationInvitationStatus::PENDING);

    $repository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $repository->method('findById')->willReturn($invitation);
    $repository->method('save')->willReturnCallback(
      static function (OrganizationInvitation $saved) use (&$persisted): void {
        $persisted = $saved;
      },
    );

    $host = $this->host($repository);

    $invalidated = $host->invalidate(new OrganizationInvitationId(self::INVITATION_ID), 'revoker-1');

    self::assertSame($invitation, $invalidated);
    self::assertSame($invitation, $persisted);
    self::assertSame(OrganizationInvitationStatus::REVOKED, $invitation->status());
    self::assertSame('revoker-1', $invitation->revokedByUserId());
  }

  #[Test]
  public function testReturnsNullWhenTheInvitationIsMissing(): void
  {
    $repository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $host = $this->host($repository);

    self::assertNull($host->invalidate(new OrganizationInvitationId(self::INVITATION_ID), 'revoker-1'));
  }

  #[Test]
  public function testReturnsNullWithoutSavingWhenTheInvitationIsNotPending(): void
  {
    $invitation = $this->invitation(OrganizationInvitationStatus::ACCEPTED);

    $repository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $repository->method('findById')->willReturn($invitation);
    $repository->method('save')->willReturnCallback(
      static function () use (&$saveCalls): void {
        $saveCalls = ($saveCalls ?? 0) + 1;
      },
    );

    $host = $this->host($repository);

    self::assertNull($host->invalidate(new OrganizationInvitationId(self::INVITATION_ID), 'revoker-1'));
    self::assertNull($saveCalls);
  }

  /**
   * Builds a trait host wired to the given repository and a pass-through transaction manager.
   */
  private function host(OrganizationInvitationRepositoryPort $repository): InvitationInvalidationHost
  {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new InvitationInvalidationHost($repository, $transactionManager);
  }

  /**
   * Reconstitutes an invitation in the given status.
   */
  private function invitation(OrganizationInvitationStatus $status): OrganizationInvitation
  {
    return OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId(self::INVITATION_ID),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655445501'),
      email: new Email('member@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655445502',
      status: $status,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );
  }
}
