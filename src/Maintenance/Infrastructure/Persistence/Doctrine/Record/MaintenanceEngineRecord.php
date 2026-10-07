<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main-database persistence record for multi-operation maintenance. */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_engines')]

class MaintenanceEngineRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'mode', type: 'string', length: 16)]
  public string $mode;

  #[ORM\Column(name: 'activated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $activatedAt;
}
