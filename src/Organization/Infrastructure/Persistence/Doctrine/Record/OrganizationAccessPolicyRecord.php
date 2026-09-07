<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationAccessPolicyRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_access_policies')]
class OrganizationAccessPolicyRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'mode', type: 'string', length: 24)]
  public string $mode;

  #[ORM\Column(name: 'role_id', type: 'string', length: 36, nullable: true)]
  public ?string $roleId = null;
}
