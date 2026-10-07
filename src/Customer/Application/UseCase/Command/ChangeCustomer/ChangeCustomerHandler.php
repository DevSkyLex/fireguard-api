<?php

declare(strict_types=1);

namespace Customer\Application\UseCase\Command\ChangeCustomer;

use Customer\Application\Contract\CustomerView;
use Customer\Application\Port\Outbound\CustomerRepositoryPort;
use Customer\Application\Service\CustomerAccessGuard;
use Customer\Domain\Event\CustomerChangedEvent;
use Customer\Domain\Exception\CustomerException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort};

/** Class ChangeCustomerHandler. Locks the scoped customer before comparing revisions. @category UseCase */
final readonly class ChangeCustomerHandler implements CommandHandler
{
  public function __construct(private CustomerRepositoryPort $customers, private CustomerAccessGuard $access, private ClockPort $clock, private TransactionManagerPort $transactions, private EventDispatcherPort $events)
  {
  }

  public function __invoke(ChangeCustomerCommand $command): ChangeCustomerResult
  {
    $this->access->assertAccess($command->actorId, $command->organizationId, true);

    return $this->transactions->transactional(function () use ($command): ChangeCustomerResult {
      $customer = $this->customers->find($command->customerId, $command->organizationId, true);
      if (null === $customer) {
        throw CustomerException::notFound();
      }
      if (null === $command->expectedRevision) {
        throw new CustomerException('customer_precondition_required', 'If-Match is required for this mutation.');
      }
      if ($customer->revision !== $command->expectedRevision) {
        throw CustomerException::stale();
      }
      $changed = match ($command->action) {
        'patch' => $customer->change($command->changes, $this->clock->now()),
        'archive' => $customer->archive($this->clock->now()),
        'restore' => $customer->restore($this->clock->now()),
        default => throw CustomerException::invalid('Unsupported customer action.'),
      };
      if ($changed !== $customer) {
        $this->customers->save($changed, $customer->revision);
        $this->events->dispatch(new CustomerChangedEvent($changed->organizationId, $changed->id, match ($command->action) {
          'archive' => 'archived', 'restore' => 'restored', default => 'updated'
        }, $changed->revision, $changed->updatedAt));
      }

      return new ChangeCustomerResult(CustomerView::fromCustomer($changed));
    });
  }
}
