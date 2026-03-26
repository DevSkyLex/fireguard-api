<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use InvalidArgumentException;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationMemberMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;

/**
 * Repository OrganizationMemberRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberRepository implements OrganizationMemberRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<OrganizationMemberRecord>
   */
  private EntityRepository $memberRepository;

  /**
   * @var EntityRepository<OrganizationMemberRoleRecord>
   */
  private EntityRepository $memberRoleRepository;

  /**
   * @var EntityRepository<OrganizationRoleRecord>
   */
  private EntityRepository $roleRepository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationMemberRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    $this->memberRepository = $entityManager->getRepository(OrganizationMemberRecord::class);
    $this->memberRoleRepository = $entityManager->getRepository(OrganizationMemberRoleRecord::class);
    $this->roleRepository = $entityManager->getRepository(OrganizationRoleRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the organization member aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationMember $member the organization member aggregate
   */
  public function save(OrganizationMember $member): void
  {
    $record = OrganizationMemberMapper::toRecord($member);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $member->organizationId());
    $record->organization = $organization;
    $existing = $this->memberRepository->find($record->id);

    if ($existing instanceof OrganizationMemberRecord) {
      $existing->isActive = $record->isActive;
      $existing->userId = $record->userId;
      $existing->organization = $organization;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Finds a member by its identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $id the member identifier
   *
   * @return ?OrganizationMember the member aggregate when found
   */
  public function findById(OrganizationMemberId $id): ?OrganizationMember
  {
    $record = $this->memberRepository->find((string) $id);

    if (!$record instanceof OrganizationMemberRecord) {
      return null;
    }

    return OrganizationMemberMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationAndUser.
   *
   * Finds a member by organization identifier and user identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return ?OrganizationMember the member aggregate when found
   */
  public function findByOrganizationAndUser(OrganizationId $organizationId, string $userId): ?OrganizationMember
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->memberRepository->findOneBy([
      'organization' => $organization,
      'userId' => $userId,
    ]);

    if (!$record instanceof OrganizationMemberRecord) {
      return null;
    }

    return OrganizationMemberMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationId.
   *
   * Lists members for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<OrganizationMember> the organization members
   */
  public function findByOrganizationId(OrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $records = $this->memberRepository->findBy([
      'organization' => $organization,
    ], [
      'joinedAt' => 'ASC',
    ]);

    return array_map(
      static fn (OrganizationMemberRecord $record): OrganizationMember => OrganizationMemberMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method findByUserId.
   *
   * Lists organization memberships for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return list<OrganizationMember> the user memberships
   */
  public function findByUserId(string $userId): array
  {
    $records = $this->memberRepository->findBy([
      'userId' => $userId,
    ], [
      'joinedAt' => 'ASC',
    ]);

    return array_map(
      static fn (OrganizationMemberRecord $record): OrganizationMember => OrganizationMemberMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method remove.
   *
   * Removes a persisted member aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationMember $member the member aggregate
   */
  public function remove(OrganizationMember $member): void
  {
    $record = $this->memberRepository->find((string) $member->id());

    if ($record instanceof OrganizationMemberRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method assignRole.
   *
   * Assigns an organization role to a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   * @param OrganizationRoleId $roleId the role identifier
   */
  public function assignRole(OrganizationMemberId $memberId, OrganizationRoleId $roleId): void
  {
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->memberRepository->find((string) $memberId);
    /** @var OrganizationRoleRecord|null $roleRecord */
    $roleRecord = $this->roleRepository->find((string) $roleId);

    if (!$memberRecord instanceof OrganizationMemberRecord || !$roleRecord instanceof OrganizationRoleRecord) {
      throw new InvalidArgumentException('Member or role not found for role assignment.');
    }

    $existing = $this->memberRoleRepository->findOneBy([
      'member' => $memberRecord,
      'role' => $roleRecord,
    ]);

    if ($existing instanceof OrganizationMemberRoleRecord) {
      return;
    }

    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $memberRecord;
    $assignment->role = $roleRecord;
    $assignment->assignedAt = new DateTimeImmutable();

    $this->entityManager->persist($assignment);
    $this->entityManager->flush();
  }

  /**
   * Method findRoleIdsForMember.
   *
   * Returns the role identifiers assigned to a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   *
   * @return list<string> the assigned role identifiers
   */
  public function findRoleIdsForMember(OrganizationMemberId $memberId): array
  {
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->memberRepository->find((string) $memberId);

    if (!$memberRecord instanceof OrganizationMemberRecord) {
      return [];
    }

    /** @var list<OrganizationMemberRoleRecord> $assignments */
    $assignments = $this->memberRoleRepository->findBy([
      'member' => $memberRecord,
    ]);

    $roleIds = array_map(
      static fn (OrganizationMemberRoleRecord $assignment): string => null !== $assignment->role ? $assignment->role->id : '',
      $assignments,
    );

    return array_values(array_filter(array_unique($roleIds), static fn (string $roleId): bool => '' !== $roleId));
  }

  /**
   * Method unassignRole.
   *
   * Removes a role assignment from a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   * @param OrganizationRoleId $roleId the role identifier to unassign
   */
  public function unassignRole(OrganizationMemberId $memberId, OrganizationRoleId $roleId): void
  {
    /** @var OrganizationMemberRecord|null $memberRecord */
    $memberRecord = $this->memberRepository->find((string) $memberId);
    /** @var OrganizationRoleRecord|null $roleRecord */
    $roleRecord = $this->roleRepository->find((string) $roleId);

    if (!$memberRecord instanceof OrganizationMemberRecord || !$roleRecord instanceof OrganizationRoleRecord) {
      return;
    }

    $assignment = $this->memberRoleRepository->findOneBy([
      'member' => $memberRecord,
      'role' => $roleRecord,
    ]);

    if (!$assignment instanceof OrganizationMemberRoleRecord) {
      return;
    }

    $this->entityManager->remove($assignment);
    $this->entityManager->flush();
  }

  /**
   * Method countByOrganizationId.
   *
   * Counts members belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the member count
   */
  public function countByOrganizationId(OrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->memberRepository->count([
      'organization' => $organization,
    ]);
  }

  /**
   * Method getPermissionNamesForUserInOrganization.
   *
   * Resolves effective permission names for a user in an organization.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<string> the effective permission names
   */
  public function getPermissionNamesForUserInOrganization(string $userId, OrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $memberRecord = $this->memberRepository->findOneBy([
      'organization' => $organization,
      'userId' => $userId,
      'isActive' => true,
    ]);

    if (!$memberRecord instanceof OrganizationMemberRecord) {
      return [];
    }

    /** @var list<OrganizationMemberRoleRecord> $assignments */
    $assignments = $this->memberRoleRepository->findBy([
      'member' => $memberRecord,
    ]);

    $permissions = [];

    foreach ($assignments as $assignment) {
      if (null === $assignment->role) {
        continue;
      }

      $rolePermissions = OrganizationSystemRoleCatalog::mergePermissions(
        roleName: $assignment->role->name,
        permissions: $assignment->role->permissions,
        isSystem: $assignment->role->isSystem,
      );

      foreach ($rolePermissions as $permission) {
        if (!in_array($permission, $permissions, true)) {
          $permissions[] = $permission;
        }
      }
    }

    return $permissions;
  }
  // #endregion
}
