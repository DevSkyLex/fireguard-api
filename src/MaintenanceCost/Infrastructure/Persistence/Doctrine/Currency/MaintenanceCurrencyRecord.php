<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Persistence\Doctrine\Currency;

use Doctrine\ORM\Mapping as ORM;

/** Main-database organization currency and immutable financial-capture lock. */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_cost_currency_settings')]
class MaintenanceCurrencyRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(type: 'string', length: 3, options: ['default' => 'EUR'])]
  public string $currency = 'EUR';

  #[ORM\Column(type: 'boolean', options: ['default' => false])]
  public bool $locked = false;
}
