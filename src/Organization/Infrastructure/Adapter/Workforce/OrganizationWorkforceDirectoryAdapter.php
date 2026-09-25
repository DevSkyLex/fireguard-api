<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Workforce;

use Doctrine\ORM\EntityManagerInterface;
use Organization\Application\Contract\Workforce\{OrganizationWorkforceContext, OrganizationWorkforceMember, OrganizationWorkforceMemberProfile};
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord};

use function array_map;
use function trim;

/**
 * Adapter OrganizationWorkforceDirectoryAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationWorkforceDirectoryAdapter implements OrganizationWorkforceDirectoryPort
{
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicitly configured main entity manager
   * @param \Shared\Application\Port\Inbound\QueryBusPort $queries dispatches read use cases through the query bus
   */
  public function __construct(private EntityManagerInterface $entityManager, private \Shared\Application\Port\Inbound\QueryBusPort $queries)
  {
  }

  /**
   * Reads the organization timezone and first-day-of-week settings.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return ?OrganizationWorkforceContext organization regional context, or null when unresolved
   */
  public function context(string $organizationId): ?OrganizationWorkforceContext
  {
    $record = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$record instanceof OrganizationRecord) {
      return null;
    }
    $regional = OrganizationSettings::fromArray($record->settings)->regional;

    return new OrganizationWorkforceContext($regional->timezone, $regional->firstDayOfWeek);
  }

  /**
   * Reads organization memberships for workload scoping and contributor checks.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<OrganizationWorkforceMember>
   */
  public function members(string $organizationId): array
  {
    $records = $this->entityManager->getRepository(OrganizationMemberRecord::class)->findBy(['organization' => $organizationId], ['id' => 'ASC']);

    return array_map(static fn (OrganizationMemberRecord $member): OrganizationWorkforceMember => new OrganizationWorkforceMember($member->id, $member->userId, $member->isActive), $records);
  }

  /**
   * Resolves member labels within the owning organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $memberIds
   *
   * @return array<string, string>
   */
  public function labels(string $organizationId, array $memberIds): array
  {
    return array_map(static fn (OrganizationWorkforceMemberProfile $profile): string => $profile->name, $this->profiles($organizationId, $memberIds));
  }

  /**
   * Resolves scoped profiles and batches organization role assignments.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes members and roles
   * @param list<string> $memberIds authorized organization membership identifiers
   *
   * @return array<string, OrganizationWorkforceMemberProfile> profiles keyed by membership identifier
   */
  public function profiles(string $organizationId, array $memberIds): array
  {
    if ([] === $memberIds) {
      return [];
    }
    $members = $this->entityManager->getRepository(OrganizationMemberRecord::class)->findBy(['organization' => $organizationId, 'id' => $memberIds]);
    /** @var list<array{memberId: string, roleName: string}> $assignments */
    $assignments = $this->entityManager->createQueryBuilder()
      ->select('workforceMember.id AS memberId', 'role.name AS roleName')
      ->from(OrganizationMemberRoleRecord::class, 'assignment')
      ->join('assignment.member', 'workforceMember')
      ->join('assignment.role', 'role')
      ->where('workforceMember.organization = :organization')
      ->andWhere('role.organization = :organization')
      ->andWhere('workforceMember.id IN (:members)')
      ->setParameter('organization', $organizationId)
      ->setParameter('members', $memberIds)
      ->orderBy('role.name', 'ASC')
      ->addOrderBy('role.id', 'ASC')
      ->getQuery()->getArrayResult();
    $roles = [];
    foreach ($assignments as $assignment) {
      $roles[$assignment['memberId']][] = $assignment['roleName'];
    }
    $profiles = [];
    foreach ($members as $member) {
      /** @var \User\Application\UseCase\Query\User\GetUser\GetUserResult $result */
      $result = $this->queries->ask(new \User\Application\UseCase\Query\User\GetUser\GetUserQuery($member->userId));
      $profile = $result->user;
      $displayName = $member->id;
      if (null !== $profile) {
        $displayName = trim($profile->firstName . ' ' . $profile->lastName) ?: $profile->username;
      }
      $profiles[$member->id] = new OrganizationWorkforceMemberProfile(
        $member->id,
        $displayName,
        $profile?->avatarUrl,
        $roles[$member->id] ?? [],
      );
    }

    return $profiles;
  }

  /**
   * Reads organization teams available to the workload filter.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<array{id: string, name: string}>
   */
  public function teams(string $organizationId): array
  {
    $teams = $this->entityManager->getRepository(\Organization\Infrastructure\Persistence\Doctrine\Record\TeamRecord::class)->findBy(['organization' => $organizationId], ['name' => 'ASC']);

    return array_map(static fn ($team): array => ['id' => $team->id, 'name' => $team->name], $teams);
  }
}
