<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'checklists')]
#[ORM\Index(name: 'idx_checklist_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_checklist_status', columns: ['status'])]
#[ORM\Index(name: 'idx_checklist_organization_status', columns: ['organization_id', 'status'])]
final class ChecklistRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'name', type: 'string', length: 255)]
  public string $name;

  #[ORM\Column(name: 'version', type: 'string', length: 50)]
  public string $version;

  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}
