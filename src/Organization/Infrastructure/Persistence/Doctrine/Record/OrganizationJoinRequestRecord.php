<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationJoinRequestRecord.
 *
 * @category Record
 *
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
  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  #[ORM\Column(name: 'email', type: 'string', length: 254)]
  public string $email;

  #[ORM\Column(name: 'domain_id', type: 'string', length: 36)]
  public string $domainId;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  public DateTimeImmutable $expiresAt;

  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'decided_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $decidedAt = null;
}
