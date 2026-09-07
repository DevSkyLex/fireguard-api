<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use DateTimeImmutable;
use Organization\Domain\Model\OrganizationJoin\{OrganizationAccessPolicy, OrganizationDomain, OrganizationJoinRequest};

/**
 * Port OrganizationJoinRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationJoinRepositoryPort
{
  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   */
  public function lock(string $organizationId): void;

  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   *
   * @return OrganizationAccessPolicy policy, invitation-only when absent
   */
  public function policy(string $organizationId): OrganizationAccessPolicy;

  /**
   * @since 1.0.0
   *
   * @param OrganizationAccessPolicy $policy configured policy
   */
  public function savePolicy(OrganizationAccessPolicy $policy): void;

  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   *
   * @return list<OrganizationDomain> domain proofs
   */
  public function domains(string $organizationId): array;

  /**
   * @since 1.0.0
   *
   * @param string $domain exact domain
   *
   * @return list<OrganizationDomain> matching proofs
   */
  public function domainsForName(string $domain): array;

  /**
   * @since 1.0.0
   *
   * @param DateTimeImmutable $before cutoff
   *
   * @return list<OrganizationDomain> due proofs
   */
  public function domainsDue(DateTimeImmutable $before): array;

  /**
   * @since 1.0.0
   *
   * @param OrganizationDomain $domain proof
   */
  public function saveDomain(OrganizationDomain $domain): void;

  /**
   * @since 1.0.0
   *
   * @param OrganizationDomain $domain proof
   */
  public function removeDomain(OrganizationDomain $domain): void;

  /**
   * @since 1.0.0
   *
   * @param string $id identifier
   *
   * @return ?OrganizationJoinRequest request
   */
  public function request(string $id): ?OrganizationJoinRequest;

  /**
   * @since 1.0.0
   *
   * @param ?string $userId applicant filter
   * @param ?string $organizationId scope filter
   *
   * @return list<OrganizationJoinRequest> requests
   */
  public function requests(?string $userId, ?string $organizationId = null): array;

  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRequest $request request
   */
  public function saveRequest(OrganizationJoinRequest $request): void;

  /**
   * @since 1.0.0
   *
   * @param string $email current email
   *
   * @return list<string> pending invitation IDs
   */
  public function invitationIds(string $email): array;
}
