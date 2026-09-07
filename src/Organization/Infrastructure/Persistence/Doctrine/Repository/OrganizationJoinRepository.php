<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Application\Port\Outbound\OrganizationJoinRepositoryPort;
use Organization\Domain\Model\OrganizationJoin\{OrganizationAccessPolicy, OrganizationDomain, OrganizationJoinRequest};
use Organization\Domain\ValueObject\OrganizationJoinMode;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationAccessPolicyRecord, OrganizationDomainRecord, OrganizationJoinRequestRecord};

use function array_map;

/**
 * Repository OrganizationJoinRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinRepository implements OrganizationJoinRepositoryPort
{
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicit main manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function lock(string $organizationId): void
  {
    $this->entityManager->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'organization.join.' . $organizationId]);
  }

  public function policy(string $organizationId): OrganizationAccessPolicy
  {
    $record = $this->entityManager->find(OrganizationAccessPolicyRecord::class, $organizationId);
    if (null === $record) {
      return new OrganizationAccessPolicy($organizationId);
    }
    $this->entityManager->refresh($record);

    return new OrganizationAccessPolicy($record->organizationId, OrganizationJoinMode::from($record->mode), $record->roleId);
  }

  public function savePolicy(OrganizationAccessPolicy $policy): void
  {
    $record = $this->entityManager->find(OrganizationAccessPolicyRecord::class, $policy->organizationId) ?? new OrganizationAccessPolicyRecord();
    $record->organizationId = $policy->organizationId;
    $record->mode = $policy->mode->value;
    $record->roleId = $policy->roleId;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  public function domains(string $organizationId): array
  {
    return array_map($this->domain(...), $this->entityManager->getRepository(OrganizationDomainRecord::class)->findBy(['organizationId' => $organizationId], ['domain' => 'ASC']));
  }

  public function domainsForName(string $domain): array
  {
    return array_map($this->domain(...), $this->entityManager->getRepository(OrganizationDomainRecord::class)->findBy(['domain' => $domain]));
  }

  public function domainsDue(DateTimeImmutable $before): array
  {
    /** @var list<OrganizationDomainRecord> $records */
    $records = $this->entityManager->createQueryBuilder()->select('d')->from(OrganizationDomainRecord::class, 'd')->where('d.lastCheckedAt IS NULL OR d.lastCheckedAt < :before')->setParameter('before', $before)->getQuery()->getResult();

    return array_map($this->domain(...), $records);
  }

  public function saveDomain(OrganizationDomain $domain): void
  {
    $record = $this->entityManager->find(OrganizationDomainRecord::class, $domain->id) ?? new OrganizationDomainRecord();
    $record->id = $domain->id;
    $record->organizationId = $domain->organizationId;
    $record->domain = $domain->domain;
    $record->dnsValue = $domain->dnsValue;
    $record->status = $domain->status;
    $record->verifiedAt = $domain->verifiedAt;
    $record->lastCheckedAt = $domain->lastCheckedAt;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  public function removeDomain(OrganizationDomain $domain): void
  {
    $record = $this->entityManager->find(OrganizationDomainRecord::class, $domain->id);
    if (null !== $record) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  public function request(string $id): ?OrganizationJoinRequest
  {
    $record = $this->entityManager->find(OrganizationJoinRequestRecord::class, $id);
    if (null === $record) {
      return null;
    }
    $this->entityManager->refresh($record);

    return $this->requestModel($record);
  }

  public function requests(?string $userId, ?string $organizationId = null): array
  {
    $criteria = [];
    if (null !== $userId) {
      $criteria['userId'] = $userId;
    }
    if (null !== $organizationId) {
      $criteria['organizationId'] = $organizationId;
    }

    return array_map($this->requestModel(...), $this->entityManager->getRepository(OrganizationJoinRequestRecord::class)->findBy($criteria, ['createdAt' => 'DESC']));
  }

  public function saveRequest(OrganizationJoinRequest $request): void
  {
    $record = $this->entityManager->find(OrganizationJoinRequestRecord::class, $request->id) ?? new OrganizationJoinRequestRecord();
    $record->id = $request->id;
    $record->organizationId = $request->organizationId;
    $record->userId = $request->userId;
    $record->email = $request->email;
    $record->domainId = $request->domainId;
    $record->createdAt = $request->createdAt;
    $record->expiresAt = $request->expiresAt;
    $record->status = $request->status;
    $record->decidedAt = $request->decidedAt;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  public function invitationIds(string $email): array
  {
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn("SELECT i.id FROM organization_invitations i JOIN organizations o ON o.id = i.organization_id WHERE LOWER(i.email) = LOWER(:email) AND i.status = 'pending' AND i.expires_at > :now AND o.status = 'active' ORDER BY i.created_at", ['email' => $email, 'now' => new DateTimeImmutable()->format('Y-m-d H:i:s')]);

    return $ids;
  }

  /**
   * @since 1.0.0
   *
   * @param OrganizationDomainRecord $record persisted proof
   *
   * @return OrganizationDomain aggregate
   */
  private function domain(OrganizationDomainRecord $record): OrganizationDomain
  {
    $this->entityManager->refresh($record);

    return new OrganizationDomain($record->id, $record->organizationId, $record->domain, $record->dnsValue, $record->status, $record->verifiedAt, $record->lastCheckedAt);
  }

  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRequestRecord $record persisted request
   *
   * @return OrganizationJoinRequest aggregate
   */
  private function requestModel(OrganizationJoinRequestRecord $record): OrganizationJoinRequest
  {
    $this->entityManager->refresh($record);

    return new OrganizationJoinRequest($record->id, $record->organizationId, $record->userId, $record->email, $record->domainId, $record->createdAt, $record->expiresAt, $record->status, $record->decidedAt);
  }
}
