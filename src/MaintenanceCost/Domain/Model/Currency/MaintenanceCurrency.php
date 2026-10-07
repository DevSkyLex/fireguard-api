<?php

declare(strict_types=1);

namespace MaintenanceCost\Domain\Model\Currency;

use MaintenanceCost\Domain\Exception\MaintenanceCostException;

use function preg_match;

/** Currency changes are allowed only before an organization captures its first financial fact. */
final readonly class MaintenanceCurrency
{
  public function __construct(public string $currency, public bool $locked = false)
  {
    if (1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
      throw MaintenanceCostException::invalid('Currency must be an uppercase three-letter code.');
    }
  }

  public function configure(string $currency): self
  {
    $next = new self($currency, $this->locked);
    if ($this->locked && $currency !== $this->currency) {
      throw MaintenanceCostException::conflict('Organization currency is locked after its first financial fact.');
    }

    return $next;
  }
}
