<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

/** Site and member edits with explicit presence flags. */
final readonly class InterventionOwnershipChanges
{
  /**
   * @param list<string>|null $participants
   */
  public function __construct(
    public ?string $siteId = null,
    public ?string $responsibleId = null,
    public ?array $participants = null,
    public bool $hasSiteId = false,
    public bool $hasResponsibleId = false,
    public bool $hasParticipants = false,
  ) {
  }
}
