<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\ConvertServiceRequest;

use Shared\Application\Message\CommandMessage;

/** Class ConvertServiceRequestCommand. Identifies one explicit repair conversion and its replay key. @category Command */
final readonly class ConvertServiceRequestCommand implements CommandMessage
{
  public function __construct(public string $actorId, public string $organizationId, public string $requestId, public ?int $expectedRevision, public string $clientOperationId, public ?string $existingInterventionId = null, public ?string $existingTaskId = null)
  {
  }
}
