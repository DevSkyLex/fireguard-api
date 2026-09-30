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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinRepository implements OrganizationJoinRepositoryPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Creates the repository with the explicitly wired main entity manager.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicit main manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  // #endregion

  // #region Methods
  /**
   * Method lock
   *
   * Takes a transaction-scoped advisory lock for changes to one organization’s join state.
   *
   * @access public
   *
   * @param string $organizationId organization whose join state is being changed
   *
   * @return void
   */
  public function lock(string $organizationId): void
  {
    $this->entityManager->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'organization.join.' . $organizationId]);
  }

  /**
   * Method policy
   *
   * Loads the current access policy, returning the default policy when no record exists.
   *
   * @access public
   *
   * @param string $organizationId organization whose join policy is requested
   *
   * @return OrganizationAccessPolicy current or default policy
   */
  public function policy(string $organizationId): OrganizationAccessPolicy
  {
    $record = $this->entityManager->find(OrganizationAccessPolicyRecord::class, $organizationId);
    if (null === $record) {
      return new OrganizationAccessPolicy($organizationId);
    }
    $this->entityManager->refresh($record);

    return new OrganizationAccessPolicy($record->organizationId, OrganizationJoinMode::from($record->mode), $record->roleId);
  }

  /**
   * Method savePolicy
   *
   * Persists the organization’s current join access policy.
   *
   * @access public
   *
   * @param OrganizationAccessPolicy $policy policy to persist
   *
   * @return void
   */
  public function savePolicy(OrganizationAccessPolicy $policy): void
  {
    $record = $this->entityManager->find(OrganizationAccessPolicyRecord::class, $policy->organizationId) ?? new OrganizationAccessPolicyRecord();
    $record->organizationId = $policy->organizationId;
    $record->mode = $policy->mode->value;
    $record->roleId = $policy->roleId;
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method domains
   *
   * Lists an organization’s verified-domain records in domain order.
   *
   * @access public
   *
   * @param string $organizationId organization whose domains are listed
   *
   * @return list<OrganizationDomain> domain aggregates
   */
  public function domains(string $organizationId): array
  {
    return array_map($this->domain(...), $this->entityManager->getRepository(OrganizationDomainRecord::class)->findBy(['organizationId' => $organizationId], ['domain' => 'ASC']));
  }

  /**
   * Method domainsForName
   *
   * Finds verified-domain records matching the supplied domain name.
   *
   * @access public
   *
   * @param string $domain domain name to match
   *
   * @return list<OrganizationDomain> matching domain aggregates
   */
  public function domainsForName(string $domain): array
  {
    return array_map($this->domain(...), $this->entityManager->getRepository(OrganizationDomainRecord::class)->findBy(['domain' => $domain]));
  }

  /**
   * Method domainsDue
   *
   * Lists domains that have never been checked or were checked before the supplied time.
   *
   * @access public
   *
   * @param DateTimeImmutable $before cutoff for the last DNS check
   *
   * @return list<OrganizationDomain> domain aggregates due for checking
   */
  public function domainsDue(DateTimeImmutable $before): array
  {
    /** @var list<OrganizationDomainRecord> $records */
    $records = $this->entityManager->createQueryBuilder()->select('d')->from(OrganizationDomainRecord::class, 'd')->where('d.lastCheckedAt IS NULL OR d.lastCheckedAt < :before')->setParameter('before', $before)->getQuery()->getResult();

    return array_map($this->domain(...), $records);
  }

  /**
   * Method saveDomain
   *
   * Inserts or updates the persisted record for a domain aggregate.
   *
   * @access public
   *
   * @param OrganizationDomain $domain domain aggregate to persist
   *
   * @return void
   */
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

  /**
   * Method removeDomain
   *
   * Removes the persisted record for a domain aggregate when it exists.
   *
   * @access public
   *
   * @param OrganizationDomain $domain domain aggregate identifying the record
   *
   * @return void
   */
  public function removeDomain(OrganizationDomain $domain): void
  {
    $record = $this->entityManager->find(OrganizationDomainRecord::class, $domain->id);
    if (null !== $record) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method request
   *
   * Loads and refreshes a join request by identifier.
   *
   * @access public
   *
   * @param string $id join request identifier
   *
   * @return OrganizationJoinRequest|null request aggregate, or null when absent
   */
  public function request(string $id): ?OrganizationJoinRequest
  {
    $record = $this->entityManager->find(OrganizationJoinRequestRecord::class, $id);
    if (null === $record) {
      return null;
    }
    $this->entityManager->refresh($record);

    return $this->requestModel($record);
  }

  /**
   * Method requests
   *
   * Lists join requests filtered by the supplied user and organization criteria.
   *
   * @access public
   *
   * @param string|null $userId optional requesting user identifier
   * @param string|null $organizationId optional organization identifier
   *
   * @return list<OrganizationJoinRequest> matching request aggregates, newest first
   */
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

  /**
   * Method saveRequest
   *
   * Inserts or updates the persisted record for a join request.
   *
   * @access public
   *
   * @param OrganizationJoinRequest $request request aggregate to persist
   *
   * @return void
   */
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

  /**
   * Method invitationIds
   *
   * Returns pending invitation IDs for the email when their organizations are active and unexpired.
   *
   * @access public
   *
   * @param string $email invitee email address to match case-insensitively
   *
   * @return list<string> matching invitation identifiers
   */
  public function invitationIds(string $email): array
  {
    /** @var list<string> */
    return $this->entityManager->getConnection()->fetchFirstColumn("SELECT i.id FROM organization_invitations i JOIN organizations o ON o.id = i.organization_id WHERE LOWER(i.email) = LOWER(:email) AND i.status = 'pending' AND i.expires_at > :now AND o.status = 'active' ORDER BY i.created_at", ['email' => $email, 'now' => new DateTimeImmutable()->format('Y-m-d H:i:s')]);
  }

  /**
   * Method domain
   *
   * Refreshes a persisted domain record and maps it to its domain aggregate.
   *
   * @access private
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
   * Method requestModel
   *
   * Refreshes a persisted join-request record and maps it to its domain aggregate.
   *
   * @access private
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
  // #endregion
}
