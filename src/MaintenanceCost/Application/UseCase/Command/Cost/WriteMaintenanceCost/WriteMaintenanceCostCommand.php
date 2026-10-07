<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost;

use Shared\Application\Message\CommandMessage;

/** Class WriteMaintenanceCostCommand. Private planning or append-only external expense request. @category Command */
final readonly class WriteMaintenanceCostCommand implements CommandMessage
{
  /**
   * @param array<string,mixed> $values
   */
  public function __construct(public string $actorId, public string $organizationId, public string $interventionId, public string $action, public array $values, public ?int $expectedRevision = null)
  {
  }
}
