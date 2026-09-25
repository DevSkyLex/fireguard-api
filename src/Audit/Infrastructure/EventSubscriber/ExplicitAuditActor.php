<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

/** Actor identity supplied by an event rather than the current request. */
final readonly class ExplicitAuditActor
{
  public function __construct(
    public ?string $userId,
    public ?string $email = null,
  ) {
  }
}
