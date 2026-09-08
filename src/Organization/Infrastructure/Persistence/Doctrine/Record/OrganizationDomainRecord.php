<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationDomainRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_domains')]
#[ORM\UniqueConstraint(name: 'uniq_org_domain_pair', columns: ['organization_id', 'domain'])]
#[ORM\Index(name: 'idx_org_domain_exact', columns: ['domain'])]
class OrganizationDomainRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'domain', type: 'string', length: 253)]
  public string $domain;

  #[ORM\Column(name: 'dns_value', type: 'string', length: 128)]
  public string $dnsValue;

  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'verified_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $verifiedAt = null;

  #[ORM\Column(name: 'last_checked_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastCheckedAt = null;
}
