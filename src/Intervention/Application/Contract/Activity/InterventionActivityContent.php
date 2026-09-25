<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Activity;

/**
 * The content of an activity, independent of its intervention and actor.
 */
final readonly class InterventionActivityContent
{
  /**
   * @param ?array<string, mixed> $payload
   */
  public function __construct(
    public string $kind,
    public string $event,
    public ?string $body,
    public ?array $payload,
  ) {
  }
}
