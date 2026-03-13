<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use InvalidArgumentException;
use Organization\Application\Port\Outbound\OrganizationInvitationRepositoryPort;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationRoleId};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationInvitationMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationInvitationRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use Shared\Domain\ValueObject\Email;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;

/**
 * Repository OrganizationInvitationRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationInvitationRepository implements OrganizationInvitationRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<OrganizationInvitationRecord>
   */
  private EntityRepository $invitationRepository;

  /**
   * @var EntityRepository<OrganizationInvitationRoleRecord>
   */
  private EntityRepository $invitationRoleRepository;

  /**
   * @var EntityRepository<OrganizationRoleRecord>
   */
  private EntityRepository $roleRepository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationInvitationRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    $this->invitationRepository = $entityManager->getRepository(OrganizationInvitationRecord::class);
    $this->invitationRoleRepository = $entityManager->getRepository(OrganizationInvitationRoleRecord::class);
    $this->roleRepository = $entityManager->getRepository(OrganizationRoleRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the organization invitation aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitation $invitation the invitation aggregate
   */
  public function save(OrganizationInvitation $invitation): void
  {
    $record = OrganizationInvitationMapper::toRecord($invitation);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $invitation->organizationId());
    $record->organization = $organization;
    $existing = $this->invitationRepository->find($record->id);

    if ($existing instanceof OrganizationInvitationRecord) {
      $existing->organization = $organization;
      $existing->email = $record->email;
      $existing->tokenHash = $record->tokenHash;
      $existing->invitedByUserId = $record->invitedByUserId;
      $existing->acceptedByUserId = $record->acceptedByUserId;
      $existing->revokedByUserId = $record->revokedByUserId;
      $existing->status = $record->status;
      $existing->expiresAt = $record->expiresAt;
      $existing->acceptedAt = $record->acceptedAt;
      $existing->revokedAt = $record->revokedAt;
      $existing->createdAt = $record->createdAt;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Finds an invitation by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $id the invitation identifier
   *
   * @return ?OrganizationInvitation the invitation aggregate when found
   */
  public function findById(OrganizationInvitationId $id): ?OrganizationInvitation
  {
    $record = $this->invitationRepository->find((string) $id);

    if (!$record instanceof OrganizationInvitationRecord) {
      return null;
    }

    return OrganizationInvitationMapper::toDomain($record);
  }

  /**
   * Method findByTokenHash.
   *
   * Finds an invitation by hashed token.
   *
   * @since 1.0.0
   *
   * @param string $tokenHash the hashed invitation token
   *
   * @return ?OrganizationInvitation the invitation aggregate when found
   */
  public function findByTokenHash(string $tokenHash): ?OrganizationInvitation
  {
    $record = $this->invitationRepository->findOneBy([
      'tokenHash' => $tokenHash,
    ]);

    if (!$record instanceof OrganizationInvitationRecord) {
      return null;
    }

    return OrganizationInvitationMapper::toDomain($record);
  }

  /**
   * Method findPendingByOrganizationAndEmail.
   *
   * Finds a pending invitation by organization and email.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param Email $email the invited email
   *
   * @return ?OrganizationInvitation the pending invitation when found
   */
  public function findPendingByOrganizationAndEmail(OrganizationId $organizationId, Email $email): ?OrganizationInvitation
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->invitationRepository->findOneBy([
      'organization' => $organization,
      'email' => (string) $email,
      'status' => 'pending',
    ], [
      'createdAt' => 'DESC',
    ]);

    if (!$record instanceof OrganizationInvitationRecord) {
      return null;
    }

    return OrganizationInvitationMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationId.
   *
   * Lists invitations for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<OrganizationInvitation> the organization invitations
   */
  public function findByOrganizationId(OrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $records = $this->invitationRepository->findBy([
      'organization' => $organization,
    ], [
      'createdAt' => 'DESC',
    ]);

    return array_map(
      static fn (OrganizationInvitationRecord $record): OrganizationInvitation => OrganizationInvitationMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method replaceRoleIds.
   *
   * Replaces role assignments for an invitation.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $invitationId the invitation identifier
   * @param list<OrganizationRoleId> $roleIds the role identifiers
   */
  public function replaceRoleIds(OrganizationInvitationId $invitationId, array $roleIds): void
  {
    /** @var OrganizationInvitationRecord|null $invitationRecord */
    $invitationRecord = $this->invitationRepository->find((string) $invitationId);

    if (!$invitationRecord instanceof OrganizationInvitationRecord) {
      throw new InvalidArgumentException('Invitation not found for role assignment.');
    }

    /** @var list<OrganizationInvitationRoleRecord> $assignments */
    $assignments = $this->invitationRoleRepository->findBy([
      'invitation' => $invitationRecord,
    ]);

    $desiredRoleIds = array_values(array_unique(array_map(
      static fn (OrganizationRoleId $roleId): string => (string) $roleId,
      $roleIds,
    )));

    foreach ($assignments as $assignment) {
      $assignedRoleId = null !== $assignment->role ? $assignment->role->id : null;

      if (null === $assignedRoleId || !in_array($assignedRoleId, $desiredRoleIds, true)) {
        $this->entityManager->remove($assignment);
      }
    }

    $existingRoleIds = array_values(array_filter(array_map(
      static fn (OrganizationInvitationRoleRecord $assignment): ?string => $assignment->role?->id,
      $assignments,
    )));

    foreach ($desiredRoleIds as $roleId) {
      if (in_array($roleId, $existingRoleIds, true)) {
        continue;
      }

      /** @var OrganizationRoleRecord|null $roleRecord */
      $roleRecord = $this->roleRepository->find($roleId);
      if (!$roleRecord instanceof OrganizationRoleRecord) {
        throw new InvalidArgumentException('Role not found for invitation role assignment.');
      }

      $assignment = new OrganizationInvitationRoleRecord();
      $assignment->invitation = $invitationRecord;
      $assignment->role = $roleRecord;
      $assignment->assignedAt = new DateTimeImmutable();

      $this->entityManager->persist($assignment);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findRoleIdsForInvitation.
   *
   * Returns role identifiers assigned to an invitation.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $invitationId the invitation identifier
   *
   * @return list<string> the assigned role identifiers
   */
  public function findRoleIdsForInvitation(OrganizationInvitationId $invitationId): array
  {
    /** @var OrganizationInvitationRecord|null $invitationRecord */
    $invitationRecord = $this->invitationRepository->find((string) $invitationId);

    if (!$invitationRecord instanceof OrganizationInvitationRecord) {
      return [];
    }

    /** @var list<OrganizationInvitationRoleRecord> $assignments */
    $assignments = $this->invitationRoleRepository->findBy([
      'invitation' => $invitationRecord,
    ]);

    $roleIds = array_map(
      static fn (OrganizationInvitationRoleRecord $assignment): string => null !== $assignment->role ? $assignment->role->id : '',
      $assignments,
    );

    return array_values(array_filter(array_unique($roleIds), static fn (string $roleId): bool => '' !== $roleId));
  }

  /**
   * Method countPendingByOrganizationId.
   *
   * Counts pending invitations for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the pending invitation count
   */
  public function countPendingByOrganizationId(OrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    return (int) $this->invitationRepository->count([
      'organization' => $organization,
      'status' => 'pending',
    ]);
  }
  // #endregion
}
