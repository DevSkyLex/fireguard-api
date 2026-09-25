<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

/** Site and members assigned to an intervention. */
final readonly class InterventionOwnership
{
  /**
   * @param list<string> $participants
   */
  public function __construct(
    public ?string $siteId,
    public ?string $responsibleId,
    public array $participants,
  ) {
  }
}
