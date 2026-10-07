<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Command\CreateCustomer;

use Shared\Application\Message\CommandMessage;

/** Class CreateCustomerCommand. Creates an internal organization customer. @category Command */
final readonly class CreateCustomerCommand implements CommandMessage
{
  /**
   * @param array<mixed> $contacts
   */
  public function __construct(public string $actorId, public string $organizationId, public string $name, public ?string $code = null, public ?string $email = null, public ?string $phone = null, public array $contacts = [])
  {
  }
}
