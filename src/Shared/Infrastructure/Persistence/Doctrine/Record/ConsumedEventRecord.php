<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Record ConsumedEventRecord. Mapped independently in auth and main; no cross-database relation. */
#[ORM\Entity]
#[ORM\Table(name: 'consumed_events')]
class ConsumedEventRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 64)]
  public string $id;

  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $consumedAt;
}
