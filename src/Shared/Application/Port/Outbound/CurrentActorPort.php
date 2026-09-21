<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/** Port CurrentActorPort. Identifies the initiating user without persisting credentials. */
interface CurrentActorPort
{
  public function userId(): ?string;
}
