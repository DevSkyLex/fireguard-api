<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\ChangeServiceRequest;

use Shared\Application\Message\CommandMessage;

/** Class ChangeServiceRequestCommand. Applies a revision-bound description or decision. @category Command */
final readonly class ChangeServiceRequestCommand implements CommandMessage
{
  /**
   * @param array<string,mixed> $changes
   */
  public function __construct(public string $actorId, public string $organizationId, public string $requestId, public string $action, public ?int $expectedRevision, public array $changes = [], public ?string $note = null, public ?string $reason = null, public ?string $equipmentId = null)
  {
  }
}
