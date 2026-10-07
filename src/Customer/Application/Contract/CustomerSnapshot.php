<?php

declare(strict_types=1);

namespace Customer\Application\Contract;

use DateTimeImmutable;

/** Class CustomerSnapshot. Immutable customer identity published to other business modules. @category Contract */
final readonly class CustomerSnapshot
{
  /**
   * @param list<array{name:string,email:?string,phone:?string,role:?string}> $contacts
   */
  public function __construct(public string $id, public string $name, public array $contacts, public ?DateTimeImmutable $archivedAt)
  {
  }
}
