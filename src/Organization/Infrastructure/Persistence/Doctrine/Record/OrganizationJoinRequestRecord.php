<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationJoinRequestRecord.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_join_requests')]
#[ORM\Index(name: 'idx_org_join_applicant', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_org_join_pending', columns: ['organization_id', 'user_id'], options: ['where' => "((status)::text = 'pending'::text)"])]
class OrganizationJoinRequestRecord
{
  // #region Properties
  /**
   * Property id
   */
  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property userId
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property email
   */
  #[ORM\Column(name: 'email', type: 'string', length: 254)]
  public string $email;

  /**
   * Property domainId
   */
  #[ORM\Column(name: 'domain_id', type: 'string', length: 36)]
  public string $domainId;

  /**
   * Property createdAt
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property expiresAt
   */
  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  public DateTimeImmutable $expiresAt;

  /**
   * Property status
   */
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  /**
   * Property decidedAt
   */
  #[ORM\Column(name: 'decided_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $decidedAt = null;
  // #endregion

}
