<?php

declare(strict_types=1);

namespace Customer\Application\Contract;

use Customer\Domain\Model\Customer\Customer;
use DateTimeImmutable;

/** Class CustomerView. Customer read projection. @category Contract */
final readonly class CustomerView
{
  /**
   * @param list<array{name:string,email:?string,phone:?string,role:?string}> $contacts
   */
  public function __construct(public string $id, public string $organizationId, public string $name, public ?string $code, public ?string $email, public ?string $phone, public array $contacts, public ?DateTimeImmutable $archivedAt, public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt, public int $revision)
  {
  }

  public static function fromCustomer(Customer $customer): self
  {
    return new self($customer->id, $customer->organizationId, $customer->name, $customer->code, $customer->email, $customer->phone, $customer->contacts, $customer->archivedAt, $customer->createdAt, $customer->updatedAt, $customer->revision);
  }
}
