<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationRoleId};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationRecord, OrganizationRoleRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationInvitationRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sort;
use function str_pad;

/**
 * Test OrganizationInvitationRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OrganizationInvitationRepository::class)]
final class OrganizationInvitationRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private OrganizationInvitationRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new OrganizationInvitationRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindByTokenHashReturnsInvitationAndNullWhenMissing(): void
  {
    $organization = $this->createOrganization('d4000000-0000-4000-8000-000000000001', 'invite-token-org');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000101',
      organization: $organization,
      email: 'token@example.com',
      tokenHash: $this->tokenHash('a'),
    ));
    $this->entityManager->flush();

    $found = $this->repository->findByTokenHash($this->tokenHash('a'));
    $missing = $this->repository->findByTokenHash($this->tokenHash('z'));

    self::assertNotNull($found);
    self::assertSame('d4000000-0000-4000-8000-000000000101', (string) $found->id());
    self::assertNull($missing);
  }

  #[Test]
  public function testFindPendingByOrganizationAndEmailScopesByStatus(): void
  {
    $organization = $this->createOrganization('d4000000-0000-4000-8000-000000000011', 'invite-pending-org');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000201',
      organization: $organization,
      email: 'pending@example.com',
      tokenHash: $this->tokenHash('b'),
      status: 'pending',
    ));
    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000202',
      organization: $organization,
      email: 'accepted@example.com',
      tokenHash: $this->tokenHash('c'),
      status: 'accepted',
    ));
    $this->entityManager->flush();

    $found = $this->repository->findPendingByOrganizationAndEmail(
      OrganizationId::fromString('d4000000-0000-4000-8000-000000000011'),
      new Email('pending@example.com'),
    );
    $notPending = $this->repository->findPendingByOrganizationAndEmail(
      OrganizationId::fromString('d4000000-0000-4000-8000-000000000011'),
      new Email('accepted@example.com'),
    );

    self::assertNotNull($found);
    self::assertSame('d4000000-0000-4000-8000-000000000201', (string) $found->id());
    self::assertNull($notPending, 'Accepted invitations are not returned as pending.');
  }

  #[Test]
  public function testCountByStatusForOrganizationIdBucketsByStatusAndExpiry(): void
  {
    $organization = $this->createOrganization('d4000000-0000-4000-8000-000000000021', 'invite-status-org');
    $this->entityManager->persist($organization);

    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000301',
      organization: $organization,
      email: 'live@example.com',
      tokenHash: $this->tokenHash('d'),
      status: 'pending',
      expiresAt: new DateTimeImmutable('2099-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000302',
      organization: $organization,
      email: 'stale@example.com',
      tokenHash: $this->tokenHash('e'),
      status: 'pending',
      expiresAt: new DateTimeImmutable('2020-01-01T00:00:00+00:00'),
    ));
    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000303',
      organization: $organization,
      email: 'joined@example.com',
      tokenHash: $this->tokenHash('f'),
      status: 'accepted',
    ));
    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000304',
      organization: $organization,
      email: 'gone@example.com',
      tokenHash: $this->tokenHash('g'),
      status: 'revoked',
    ));
    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000305',
      organization: $organization,
      email: 'lapsed@example.com',
      tokenHash: $this->tokenHash('h'),
      status: 'expired',
    ));
    $this->entityManager->flush();

    $id = OrganizationId::fromString('d4000000-0000-4000-8000-000000000021');
    $counts = $this->repository->countByStatusForOrganizationId($id);

    self::assertSame(1, $counts['pending']);
    self::assertSame(1, $counts['accepted']);
    self::assertSame(1, $counts['revoked']);
    self::assertSame(2, $counts['expired'], 'Explicitly expired plus lapsed-pending invitations are both expired.');
    self::assertSame(1, $this->repository->countPendingByOrganizationId($id));
    self::assertSame(5, $this->repository->countByOrganizationId($id));
  }

  #[Test]
  public function testReplaceRoleIdsPersistsAssignmentsRetrievableByFindRoleIds(): void
  {
    $organization = $this->createOrganization('d4000000-0000-4000-8000-000000000031', 'invite-roles-org');
    $this->entityManager->persist($organization);

    $roleA = $this->createRole('d4000000-0000-4000-8000-000000000401', $organization, 'inspector');
    $roleB = $this->createRole('d4000000-0000-4000-8000-000000000402', $organization, 'auditor');
    $roleC = $this->createRole('d4000000-0000-4000-8000-000000000403', $organization, 'manager');
    $this->entityManager->persist($roleA);
    $this->entityManager->persist($roleB);
    $this->entityManager->persist($roleC);

    $this->entityManager->persist($this->createInvitation(
      id: 'd4000000-0000-4000-8000-000000000501',
      organization: $organization,
      email: 'roles@example.com',
      tokenHash: $this->tokenHash('i'),
    ));
    $this->entityManager->flush();

    $invitationId = OrganizationInvitationId::fromString('d4000000-0000-4000-8000-000000000501');

    $this->repository->replaceRoleIds($invitationId, [
      OrganizationRoleId::fromString('d4000000-0000-4000-8000-000000000401'),
      OrganizationRoleId::fromString('d4000000-0000-4000-8000-000000000402'),
    ]);

    $afterInitial = $this->repository->findRoleIdsForInvitation($invitationId);
    sort($afterInitial);

    self::assertSame([
      'd4000000-0000-4000-8000-000000000401',
      'd4000000-0000-4000-8000-000000000402',
    ], $afterInitial);

    // Replacing swaps the assignment set: role A stays, B drops, C is added.
    $this->repository->replaceRoleIds($invitationId, [
      OrganizationRoleId::fromString('d4000000-0000-4000-8000-000000000401'),
      OrganizationRoleId::fromString('d4000000-0000-4000-8000-000000000403'),
    ]);

    $afterReplace = $this->repository->findRoleIdsForInvitation($invitationId);
    sort($afterReplace);

    self::assertSame([
      'd4000000-0000-4000-8000-000000000401',
      'd4000000-0000-4000-8000-000000000403',
    ], $afterReplace);
  }

  private function tokenHash(string $seed): string
  {
    return str_pad($seed, 64, $seed);
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Org ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'd4000000-0000-4000-8000-0000000000aa';
    $organization->createdByUserId = 'd4000000-0000-4000-8000-0000000000aa';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $organization->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $organization;
  }

  private function createRole(string $id, OrganizationRecord $organization, string $name): OrganizationRoleRecord
  {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->description = 'Invitation role fixture';
    $role->permissions = ['organization.read'];
    $role->isSystem = false;
    $role->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $role;
  }

  private function createInvitation(
    string $id,
    OrganizationRecord $organization,
    string $email,
    string $tokenHash,
    string $status = 'pending',
    ?DateTimeImmutable $expiresAt = null,
  ): OrganizationInvitationRecord {
    $invitation = new OrganizationInvitationRecord();
    $invitation->id = $id;
    $invitation->organization = $organization;
    $invitation->email = $email;
    $invitation->tokenHash = $tokenHash;
    $invitation->invitedByUserId = 'd4000000-0000-4000-8000-0000000000bb';
    $invitation->status = $status;
    $invitation->expiresAt = $expiresAt ?? new DateTimeImmutable('2099-01-01T00:00:00+00:00');
    $invitation->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $invitation->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $invitation;
  }
}
