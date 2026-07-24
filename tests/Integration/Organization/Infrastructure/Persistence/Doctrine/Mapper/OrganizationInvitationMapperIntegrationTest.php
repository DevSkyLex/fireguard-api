<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationInvitationMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationRecord};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function str_repeat;

/**
 * Test OrganizationInvitationMapper.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OrganizationInvitationMapper::class)]
final class OrganizationInvitationMapperIntegrationTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'e5100000-0000-4000-8000-000000000001';

  private const string PENDING_INVITATION_ID = 'e5100000-0000-4000-8000-0000000000a1';

  private const string CLOSED_INVITATION_ID = 'e5100000-0000-4000-8000-0000000000a2';

  private const string ROUND_TRIP_INVITATION_ID = 'e5100000-0000-4000-8000-0000000000a3';

  private const string INVITER_ID = 'e5100000-0000-4000-8000-0000000000b1';

  private const string ACCEPTOR_ID = 'e5100000-0000-4000-8000-0000000000b2';

  private const string REVOKER_ID = 'e5100000-0000-4000-8000-0000000000b3';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Mapper Test Org';
    $organization->slug = 'invitation-mapper-test-org';
    $organization->ownerUserId = self::INVITER_ID;
    $organization->createdByUserId = self::INVITER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-05-01T00:00:00+00:00');
    $organization->updatedAt = new DateTimeImmutable('2026-05-01T00:00:00+00:00');
    $this->entityManager->persist($organization);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testToDomainReconstitutesAggregateFromPersistedPendingRecord(): void
  {
    $record = new OrganizationInvitationRecord();
    $record->id = self::PENDING_INVITATION_ID;
    $record->organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $record->email = 'pending@example.com';
    $record->tokenHash = str_repeat('a', 64);
    $record->invitedByUserId = self::INVITER_ID;
    $record->status = 'pending';
    $record->expiresAt = new DateTimeImmutable('2099-01-01T00:00:00+00:00');
    $record->createdAt = new DateTimeImmutable('2026-05-02T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-05-02T08:00:00+00:00');
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    /** @var OrganizationInvitationRecord $reloaded */
    $reloaded = $this->entityManager->find(OrganizationInvitationRecord::class, self::PENDING_INVITATION_ID);
    $invitation = OrganizationInvitationMapper::toDomain($reloaded);

    self::assertSame(self::PENDING_INVITATION_ID, (string) $invitation->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $invitation->organizationId());
    self::assertSame('pending@example.com', (string) $invitation->email());
    self::assertSame(str_repeat('a', 64), $invitation->tokenHash());
    self::assertSame(self::INVITER_ID, $invitation->invitedByUserId());
    self::assertSame(OrganizationInvitationStatus::PENDING, $invitation->status());
    self::assertSame('2099-01-01T00:00:00+00:00', $invitation->expiresAt()->format('Y-m-d\TH:i:sP'));
    self::assertSame('2026-05-02T08:00:00+00:00', $invitation->createdAt()->format('Y-m-d\TH:i:sP'));
    self::assertNull($invitation->acceptedAt());
    self::assertNull($invitation->acceptedByUserId());
    self::assertNull($invitation->revokedAt());
    self::assertNull($invitation->revokedByUserId());
  }

  #[Test]
  public function testToDomainCarriesAcceptanceAndRevocationMetadata(): void
  {
    $record = new OrganizationInvitationRecord();
    $record->id = self::CLOSED_INVITATION_ID;
    $record->organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $record->email = 'closed@example.com';
    $record->tokenHash = str_repeat('b', 64);
    $record->invitedByUserId = self::INVITER_ID;
    $record->acceptedByUserId = self::ACCEPTOR_ID;
    $record->revokedByUserId = self::REVOKER_ID;
    $record->status = 'accepted';
    $record->expiresAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $record->acceptedAt = new DateTimeImmutable('2026-05-10T09:30:00+00:00');
    $record->revokedAt = new DateTimeImmutable('2026-05-11T10:15:00+00:00');
    $record->createdAt = new DateTimeImmutable('2026-05-02T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-05-11T10:15:00+00:00');
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    /** @var OrganizationInvitationRecord $reloaded */
    $reloaded = $this->entityManager->find(OrganizationInvitationRecord::class, self::CLOSED_INVITATION_ID);
    $invitation = OrganizationInvitationMapper::toDomain($reloaded);

    self::assertSame(OrganizationInvitationStatus::ACCEPTED, $invitation->status());
    self::assertSame(self::ACCEPTOR_ID, $invitation->acceptedByUserId());
    self::assertSame(self::REVOKER_ID, $invitation->revokedByUserId());
    self::assertNotNull($invitation->acceptedAt());
    self::assertSame('2026-05-10T09:30:00+00:00', $invitation->acceptedAt()->format('Y-m-d\TH:i:sP'));
    self::assertNotNull($invitation->revokedAt());
    self::assertSame('2026-05-11T10:15:00+00:00', $invitation->revokedAt()->format('Y-m-d\TH:i:sP'));
  }

  #[Test]
  public function testToDomainThrowsWhenRecordHasNoOrganization(): void
  {
    $record = new OrganizationInvitationRecord();
    $record->id = self::PENDING_INVITATION_ID;
    $record->email = 'orphan@example.com';
    $record->tokenHash = str_repeat('c', 64);
    $record->invitedByUserId = self::INVITER_ID;
    $record->status = 'pending';
    $record->expiresAt = new DateTimeImmutable('2099-01-01T00:00:00+00:00');
    $record->createdAt = new DateTimeImmutable('2026-05-02T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-05-02T08:00:00+00:00');

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Organization invitation record must reference an organization.');

    OrganizationInvitationMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordMapsEveryFieldFromDomainAggregate(): void
  {
    $invitation = OrganizationInvitation::reconstitute(
      id: OrganizationInvitationId::fromString(self::CLOSED_INVITATION_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      email: new Email('mapped@example.com'),
      tokenHash: str_repeat('d', 64),
      invitedByUserId: self::INVITER_ID,
      status: OrganizationInvitationStatus::REVOKED,
      expiresAt: new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-05-02T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-05-11T10:15:00+00:00'),
      acceptedAt: new DateTimeImmutable('2026-05-10T09:30:00+00:00'),
      acceptedByUserId: self::ACCEPTOR_ID,
      revokedAt: new DateTimeImmutable('2026-05-11T10:15:00+00:00'),
      revokedByUserId: self::REVOKER_ID,
    );

    $record = OrganizationInvitationMapper::toRecord($invitation);

    self::assertSame(self::CLOSED_INVITATION_ID, $record->id);
    self::assertSame('mapped@example.com', $record->email);
    self::assertSame(str_repeat('d', 64), $record->tokenHash);
    self::assertSame(self::INVITER_ID, $record->invitedByUserId);
    self::assertSame(self::ACCEPTOR_ID, $record->acceptedByUserId);
    self::assertSame(self::REVOKER_ID, $record->revokedByUserId);
    self::assertSame('revoked', $record->status);
    self::assertSame('2026-06-01T00:00:00+00:00', $record->expiresAt->format('Y-m-d\TH:i:sP'));
    self::assertNotNull($record->acceptedAt);
    self::assertSame('2026-05-10T09:30:00+00:00', $record->acceptedAt->format('Y-m-d\TH:i:sP'));
    self::assertNotNull($record->revokedAt);
    self::assertSame('2026-05-11T10:15:00+00:00', $record->revokedAt->format('Y-m-d\TH:i:sP'));
    self::assertSame('2026-05-02T08:00:00+00:00', $record->createdAt->format('Y-m-d\TH:i:sP'));
    self::assertSame('2026-05-11T10:15:00+00:00', $record->updatedAt->format('Y-m-d\TH:i:sP'));
    // The mapper leaves the organization association for the repository to wire.
    self::assertNull($record->organization);
  }

  #[Test]
  public function testRoundTripThroughDatabasePreservesAggregateState(): void
  {
    $original = OrganizationInvitation::reconstitute(
      id: OrganizationInvitationId::fromString(self::ROUND_TRIP_INVITATION_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      email: new Email('round-trip@example.com'),
      tokenHash: str_repeat('e', 64),
      invitedByUserId: self::INVITER_ID,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('2099-01-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-05-02T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-05-02T08:00:00+00:00'),
    );

    $record = OrganizationInvitationMapper::toRecord($original);
    $record->organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();

    /** @var OrganizationInvitationRecord $reloaded */
    $reloaded = $this->entityManager->find(OrganizationInvitationRecord::class, self::ROUND_TRIP_INVITATION_ID);
    $restored = OrganizationInvitationMapper::toDomain($reloaded);

    self::assertTrue($restored->id()->equals($original->id()));
    self::assertTrue($restored->organizationId()->equals($original->organizationId()));
    self::assertTrue($restored->email()->equals($original->email()));
    self::assertSame($original->tokenHash(), $restored->tokenHash());
    self::assertSame($original->invitedByUserId(), $restored->invitedByUserId());
    self::assertSame($original->status(), $restored->status());
    self::assertSame(
      $original->expiresAt()->format('Y-m-d\TH:i:sP'),
      $restored->expiresAt()->format('Y-m-d\TH:i:sP'),
    );
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM organization_invitation_roles WHERE invitation_id IN (
        SELECT id FROM organization_invitations WHERE organization_id = :organizationId
      )',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organization_invitations WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
