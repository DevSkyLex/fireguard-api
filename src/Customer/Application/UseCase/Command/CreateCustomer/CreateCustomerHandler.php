<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Command\CreateCustomer;

use Customer\Application\Contract\CustomerView;
use Customer\Application\Port\Outbound\CustomerRepositoryPort;
use Customer\Application\Service\CustomerAccessGuard;
use Customer\Domain\Event\CustomerChangedEvent;
use Customer\Domain\Model\Customer\Customer;
use Customer\Domain\ValueObject\CustomerDetails;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};

/** Class CreateCustomerHandler. Authorizes and commits a validated customer on main. @category UseCase */
final readonly class CreateCustomerHandler implements CommandHandler
{
  public function __construct(private CustomerRepositoryPort $customers, private CustomerAccessGuard $access, private ClockPort $clock, private UuidGeneratorPort $ids, private TransactionManagerPort $transactions, private EventDispatcherPort $events)
  {
  }

  public function __invoke(CreateCustomerCommand $command): CreateCustomerResult
  {
    $this->access->assertAccess($command->actorId, $command->organizationId, true);

    return $this->transactions->transactional(function () use ($command): CreateCustomerResult {
      $customer = Customer::create($this->ids->generate(), $command->organizationId, new CustomerDetails($command->name, $command->code, $command->email, $command->phone, $command->contacts), $this->clock->now());
      $this->customers->save($customer);
      $this->events->dispatch(new CustomerChangedEvent($customer->organizationId, $customer->id, 'created', $customer->revision, $customer->updatedAt));

      return new CreateCustomerResult(CustomerView::fromCustomer($customer));
    });
  }
}
