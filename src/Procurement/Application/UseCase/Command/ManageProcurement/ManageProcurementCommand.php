<?php

declare(strict_types=1);

namespace Procurement\Application\UseCase\Command\ManageProcurement;

use Shared\Application\Message\CommandMessage;

/** Authorized operation inputs preserve explicit missing fields and optimistic revision. */
final readonly class ManageProcurementCommand implements CommandMessage
{
  /**
   * @param array<string,mixed> $payload
   */
  public function __construct(public string $actorId, public string $organizationId, public string $action, public ?string $id = null, public ?int $expectedRevision = null, public array $payload = [])
  {
  }
}
