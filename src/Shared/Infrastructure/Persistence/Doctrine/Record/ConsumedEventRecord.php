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
  // #region Properties
  /**
   * Property id
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 64)]
  public string $id;

  /**
   * Property consumedAt
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $consumedAt;
  // #endregion
}
