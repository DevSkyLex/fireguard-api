<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Command\ChangeCustomer;

use Shared\Application\Message\CommandMessage;

/** Class ChangeCustomerCommand. Applies a revision-bound patch or archival action. @category Command */
final readonly class ChangeCustomerCommand implements CommandMessage
{
  /**
   * @param array<string,mixed> $changes
   */
  public function __construct(public string $actorId, public string $organizationId, public string $customerId, public string $action, public ?int $expectedRevision, public array $changes = [])
  {
  }
}
