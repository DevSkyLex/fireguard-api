<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Activity;

/**
 * A system event or comment to append to an intervention activity feed.
 */
final readonly class InterventionActivityAppendRequest
{
  public function __construct(
    public string $interventionId,
    public string $organizationId,
    public ?string $actorId,
    public InterventionActivityContent $content,
    public ?string $clientId = null,
  ) {
  }
}
